<?php

namespace Javaabu\Imports\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Javaabu\Imports\Enums\Duplicates;
use Javaabu\Imports\Enums\Formats;
use Javaabu\Imports\Enums\ImportStatuses;
use Javaabu\Imports\Http\Requests\ImportOptionsRequest;
use Javaabu\Imports\Http\Requests\UploadImportRequest;
use Javaabu\Imports\Http\Resources\ImportResource;
use Javaabu\Imports\Importer;
use Javaabu\Imports\Imports;
use Javaabu\Imports\Models\Import;
use Javaabu\Imports\Processing\Preview;
use Javaabu\Imports\Processing\RowFailure;
use Javaabu\Imports\Routing\ImportRoutes;
use Javaabu\Imports\Support\Broadcasting;
use Javaabu\Imports\Views\ViewRenderer;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, map, preview, run, follow, cancel and delete imports, and download templates and
 * failure reports
 *
 * Every action answers JSON when the request expects it, and a Blade or Inertia page
 * otherwise. Override it with `config('imports.controllers.imports')`, the `controller`
 * option of `Route::imports()`, or by binding a subclass in the container.
 */
class ImportsController extends Controller
{
    public function __construct(
        protected ViewRenderer $views,
    ) {}

    public function index(Request $request): View|Responsable|JsonResponse
    {
        $this->authorizeAbility($request, 'viewAny', Imports::importModel());

        $user = $request->user();
        $sees_others = $user->can('viewOthers', Imports::importModel());
        $per_page = min(max($request->integer('per_page', 20), 1), 100);

        $query = $this->query($request)
            ->with('user')
            ->latest('id');

        if (! $sees_others || $request->boolean('mine')) {
            $query->ownedBy($user);
        }

        if ($status = ImportStatuses::tryFrom((string) $request->query('status'))) {
            $query->status($status);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        $imports = $query->paginate($per_page)->withQueryString();

        if ($request->expectsJson()) {
            return ImportResource::collection($imports)
                ->additional(['meta' => [...$this->meta($request), 'available' => $this->availableImporters($request)]])
                ->response();
        }

        return $this->views->render('imports.index', [
            'imports' => $this->views->isInertia()
                ? ImportResource::collection($imports)
                : $imports,
            'available' => $this->availableImporters($request),
            'formats' => $this->formats(),
            'accept' => implode(',', array_map(fn (string $extension) => ".{$extension}", Formats::extensions())),
            'sees_others' => $sees_others,
            'filters' => [
                'status' => $status?->value,
                'type' => $type ?: null,
                'mine' => $request->boolean('mine'),
            ],
            'statuses' => collect(ImportStatuses::cases())
                ->map(fn (ImportStatuses $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            ...$this->meta($request),
            'strings' => $this->views->isInertia() ? $this->strings() : null,
            'title' => __('Imports'),
        ]);
    }

    /**
     * Upload a file: it's stored as a draft, and the user maps its columns next
     */
    public function store(UploadImportRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility($request, 'create', Imports::importModel());

        $importer = $this->makeImporter($request, $request->validated('type'));

        abort_unless($importer->authorize($request->user()), 403);

        $import = $importer->upload($request->file('file'), $request->user(), $request->validated('type'));

        if ($request->expectsJson()) {
            return (new ImportResource($import))
                ->additional(['preview' => $this->previewData($import)])
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->route($this->routeName($request, 'show'), $import->uuid);
    }

    public function show(Request $request, string $import): View|Responsable|JsonResponse
    {
        $import = $this->findImport($request, $import);

        $this->authorizeAbility($request, 'view', $import);

        $import->load('user');
        $preview = $import->isDraft() ? $this->previewData($import) : null;

        if ($request->expectsJson()) {
            return (new ImportResource($import))
                ->additional([
                    'preview' => $preview,
                    'failures' => $this->failures($import),
                    'meta' => $this->meta($request),
                ])
                ->response();
        }

        return $this->views->render('imports.show', [
            'import' => $this->views->isInertia()
                ? (new ImportResource($import))->resolve($request)
                : $import,
            'preview' => $preview,
            'failures' => $this->failures($import),
            'duplicate_options' => collect(Duplicates::cases())
                ->map(fn (Duplicates $option) => ['value' => $option->value, 'label' => $option->label()])
                ->all(),
            ...$this->meta($request),
            'strings' => $this->views->isInertia() ? $this->strings() : null,
            'title' => $import->name,
        ]);
    }

    /**
     * Save the mapping and options of a draft, and preview them
     */
    public function preview(ImportOptionsRequest $request, string $import): RedirectResponse|JsonResponse
    {
        $import = $this->findImport($request, $import);

        $this->authorizeAbility($request, 'update', $import);

        if (! $import->isDraft()) {
            return $this->failure($request, __('This import has already started.'), 409);
        }

        $this->configure($request, $import);

        if ($request->expectsJson()) {
            return (new ImportResource($import))
                ->additional(['preview' => $this->previewData($import)])
                ->response();
        }

        return redirect()->route($this->routeName($request, 'show'), $import->uuid);
    }

    /**
     * Queue the import, with the mapping and options sent (or saved)
     */
    public function run(ImportOptionsRequest $request, string $import): RedirectResponse|JsonResponse
    {
        $import = $this->findImport($request, $import);

        $this->authorizeAbility($request, 'update', $import);

        if (! in_array($import->status, [ImportStatuses::DRAFT, ImportStatuses::FAILED, ImportStatuses::CANCELLED], true)) {
            return $this->failure($request, __('This import has already started.'), 409);
        }

        abort_unless($import->importer()->authorize($request->user()), 403);

        $this->configure($request, $import, require_complete: true);

        $import->run();

        if ($request->expectsJson()) {
            return (new ImportResource($import->refresh()))->response()->setStatusCode(202);
        }

        return redirect()
            ->route($this->routeName($request, 'show'), $import->uuid)
            ->with('success', $import->dry_run
                ? __('Your file is being checked. Nothing will be saved.')
                : __('Your import has started. We will let you know when it is done.'));
    }

    /**
     * Download an importer's template: its headings and an example row
     */
    public function template(Request $request, string $type): BinaryFileResponse
    {
        $this->authorizeAbility($request, 'create', Imports::importModel());

        $importer = $this->makeImporter($request, $type);

        abort_unless($importer->authorize($request->user()), 403);

        $format = Formats::tryFrom((string) $request->query('format', Formats::XLSX->value));

        abort_unless($format && in_array($format, Formats::enabled(), true), 404);

        return $importer->downloadTemplate($format);
    }

    public function report(Request $request, string $import): StreamedResponse|JsonResponse
    {
        $import = $this->findImport($request, $import);

        $this->authorizeAbility($request, 'download', $import);

        return $this->reportResponse($import);
    }

    /**
     * The link in notifications: signed and expiring, no login needed
     */
    public function signedReport(Request $request, string $import): StreamedResponse|JsonResponse
    {
        return $this->reportResponse($this->findImport($request, $import));
    }

    public function cancel(Request $request, string $import): RedirectResponse|JsonResponse
    {
        $import = $this->findImport($request, $import);

        $this->authorizeAbility($request, 'cancel', $import);

        if (! $import->cancel()) {
            return $this->failure($request, __('This import can no longer be cancelled.'), 409);
        }

        if ($request->expectsJson()) {
            return (new ImportResource($import))->response();
        }

        return back()->with('success', __('Import cancelled'));
    }

    public function destroy(Request $request, string $import): RedirectResponse|JsonResponse
    {
        $import = $this->findImport($request, $import);

        $this->authorizeAbility($request, 'delete', $import);

        $import->cancel();
        $import->delete();

        if ($request->expectsJson()) {
            return response()->json(null, 204);
        }

        return redirect()
            ->route($this->routeName($request, 'index'))
            ->with('success', __('Import deleted'));
    }

    protected function configure(ImportOptionsRequest $request, Import $import, bool $require_complete = false): void
    {
        $import->configure(
            $request->mapping(),
            $request->validated('duplicates'),
            $request->has('dry_run') ? $request->boolean('dry_run') : null,
            $require_complete,
        );
    }

    protected function reportResponse(Import $import): StreamedResponse|JsonResponse
    {
        abort_unless($import->report_path && $import->failed_rows > 0, 404);

        abort_if($import->hasExpired() || ! $import->reportExists(), 410, __('This report has expired.'));

        return $import->downloadReport();
    }

    /**
     * The draft's preview, or an error when its file can't be read
     *
     * @return array<string, mixed>
     */
    protected function previewData(Import $import): array
    {
        try {
            return $import->preview()->toArray();
        } catch (RuntimeException $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function failures(Import $import): array
    {
        return array_map(fn (RowFailure $failure) => $failure->toArray(), $import->failureSamples());
    }

    protected function query(Request $request): Builder
    {
        return Imports::importModel()::query();
    }

    protected function findImport(Request $request, string $uuid): Import
    {
        return $this->query($request)->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * The registered importer for a type, made from the request when it has fromRequest()
     */
    protected function makeImporter(Request $request, string $type): Importer
    {
        $class = Imports::importerClass($type);

        abort_unless($class, 404);

        if (method_exists($class, 'fromRequest')) {
            return $class::fromRequest($request);
        }

        return new $class;
    }

    /**
     * The registered importers the user may run
     *
     * @return array<int, array{type: string, label: string, templates: array<string, string>}>
     */
    protected function availableImporters(Request $request): array
    {
        $available = [];

        foreach (Imports::registered() as $type => $class) {
            $importer = new $class;

            if (! $importer->authorize($request->user())) {
                continue;
            }

            $templates = [];

            foreach (Formats::enabled() as $format) {
                $templates[$format->value] = route($this->routeName($request, 'template'), ['type' => $type, 'format' => $format->value]);
            }

            $available[] = ['type' => $type, 'label' => $importer->label(), 'templates' => $templates];
        }

        return $available;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function formats(): array
    {
        return array_map(
            fn (Formats $format) => ['value' => $format->value, 'label' => $format->label()],
            Formats::enabled(),
        );
    }

    /**
     * What a page needs to follow progress: broadcasting or polling, and the route names
     *
     * @return array<string, mixed>
     */
    protected function meta(Request $request): array
    {
        return [
            'broadcasting' => Broadcasting::enabled(),
            'poll_interval' => Broadcasting::pollInterval(),
            'routes' => $this->routeNames($request),
            'urls' => [
                'index' => route($this->routeName($request, 'index')),
                'store' => route($this->routeName($request, 'store')),
            ],
        ];
    }

    /**
     * The translated text of the pages, for the Inertia pages (Blade views use __() directly)
     *
     * @return array<string, string>
     */
    protected function strings(): array
    {
        return [
            'title' => __('Imports'),
            'new_import' => __('New import'),
            'import' => __('Import'),
            'file' => __('File'),
            'upload' => __('Upload and map columns'),
            'templates' => __('Templates'),
            'download_template' => __('Download template'),
            'status' => __('Status'),
            'all_statuses' => __('All statuses'),
            'only_mine' => __('Only my imports'),
            'filter' => __('Filter'),
            'name' => __('Name'),
            'progress' => __('Progress'),
            'rows' => __('Rows'),
            'started_by' => __('Started by'),
            'created' => __('Created'),
            'actions' => __('Actions'),
            'view' => __('View'),
            'cancel' => __('Cancel'),
            'delete' => __('Delete'),
            'confirm_cancel' => __('Cancel this import? Rows already saved stay saved.'),
            'confirm_delete' => __('Delete this import and its files?'),
            'no_imports' => __('No imports yet'),
            'no_imports_message' => __('Upload a file to import it.'),
            'rows_of' => __(':processed of :total rows'),
            'back' => __('Back to imports'),
            'map_columns' => __('Map columns'),
            'map_columns_help' => __('Choose the field each column of your file fills. Columns set to "Don\'t import" are ignored.'),
            'column_in_file' => __('Column in your file'),
            'sample_values' => __('Sample values'),
            'field' => __('Field'),
            'dont_import' => __('Don\'t import'),
            'required' => __('Required'),
            'missing_required' => __('Choose a column for these required fields:'),
            'existing_records' => __('Existing records'),
            'dry_run' => __('Only check the file, don\'t save anything'),
            'preview' => __('Preview'),
            'preview_help' => __('The first :count rows, checked with this mapping. Nothing has been saved yet.'),
            'row' => __('Row'),
            'errors' => __('Errors'),
            'result' => __('Result'),
            'no_errors' => __('No problems found in these rows.'),
            'run' => __('Start import'),
            'run_dry' => __('Check the file'),
            'retry' => __('Try again'),
            'created_rows' => __('New'),
            'updated_rows' => __('Updated'),
            'skipped_rows' => __('Skipped'),
            'failed_rows' => __('Failed'),
            'failed_rows_help' => __('These rows were not imported. Download the report, fix the rows and import it again.'),
            'download_report' => __('Download failed rows'),
            'dry_run_notice' => __('This was a dry run: nothing was saved.'),
            'error' => __('Error'),
            'file_unavailable' => __('The uploaded file is no longer available.'),
        ];
    }

    protected function failure(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('error', $message);
    }

    protected function authorizeAbility(Request $request, string $ability, mixed $arguments): void
    {
        $user = $request->user();

        abort_unless($user && $user->can($ability, $arguments), 403);
    }

    protected function routeName(Request $request, string $action): string
    {
        return ImportRoutes::namePrefix($request) . ".{$action}";
    }

    /**
     * Names of the routes, honouring the prefix the app registered them under
     *
     * @return array<string, string>
     */
    protected function routeNames(Request $request): array
    {
        return collect(['index', 'store', 'template', 'show', 'preview', 'run', 'report', 'cancel', 'destroy'])
            ->mapWithKeys(fn (string $action) => [$action => $this->routeName($request, $action)])
            ->all();
    }
}
