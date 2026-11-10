import { Alert, Card, LinkButton, Status } from "@javaabu/forms";
import { router } from "@inertiajs/react";
import { useMemo } from "react";
import { ColumnMapping } from "../../components/ColumnMapping";
import { FailuresTable } from "../../components/FailuresTable";
import { ImportActions } from "../../components/ImportActions";
import { ImportCounts } from "../../components/ImportCounts";
import { ImportProgress } from "../../components/ImportProgress";
import { PreviewTable } from "../../components/PreviewTable";
import { useImportProgress } from "../../hooks/useImportProgress";
import type { ImportsShowProps } from "../../types";

function isPreviewError(preview: NonNullable<ImportsShowProps["preview"]>): preview is { error: string } {
  return typeof preview.error === "string";
}

/**
 * imports::Imports/Show — a draft's mapping step and preview, or a running or finished
 * import with live progress, its counts and failed rows
 */
export default function Show(props: ImportsShowProps) {
  const { strings, urls, preview } = props;
  const initial = useMemo(() => [props.import], [props.import]);

  const item =
    useImportProgress(initial, {
      broadcasting: props.broadcasting,
      pollInterval: props.poll_interval,
      onFinished: () => router.reload({ only: ["import", "failures"] }),
    })[0] ?? props.import;

  return (
    <div className="flex flex-col gap-6" data-imports>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold">{props.title}</h1>
          <p className="text-muted-foreground text-sm" dir="auto">
            {item.filename}
          </p>
        </div>
        <LinkButton url={urls.index} color="secondary" size="sm" startIcon="chevron-left">
          {strings.back}
        </LinkButton>
      </div>

      {item.is_draft ? (
        <>
          {preview && isPreviewError(preview) ? (
            <Alert type="danger">{preview.error}</Alert>
          ) : preview ? (
            <>
              <ColumnMapping import={item} preview={preview} duplicateOptions={props.duplicate_options} strings={strings} />
              {preview.ready ? <PreviewTable preview={preview} strings={strings} /> : null}
            </>
          ) : null}
          <ImportActions import={item} strings={strings} />
        </>
      ) : (
        <>
          <Card footer={<ImportActions import={item} strings={strings} />} data-import-row={item.id}>
            <div className="flex flex-col gap-6">
              <div className="flex flex-wrap items-center gap-6">
                <Status color={item.status_color} label={item.status_label} />
                <div className="min-w-64 grow">
                  <ImportProgress import={item} strings={strings} />
                </div>
              </div>

              {item.dry_run ? <Alert type="info">{strings.dry_run_notice}</Alert> : null}

              <ImportCounts import={item} strings={strings} />

              {item.error ? (
                <div>
                  <p className="text-muted-foreground text-sm">{strings.error}</p>
                  <p className="text-destructive">{item.error}</p>
                </div>
              ) : null}
            </div>
          </Card>

          <FailuresTable failures={props.failures} strings={strings} />
        </>
      )}
    </div>
  );
}
