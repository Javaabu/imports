<?php

namespace Javaabu\Imports\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Imports\Models\Import;
use Javaabu\Permissions\Policies\ResourcePolicy;

/**
 * Who may upload, map, run, follow, cancel and delete imports
 *
 * | Ability                    | Permission                                                     |
 * | -------------------------- | -------------------------------------------------------------- |
 * | viewAny, create            | view_imports or view_others_imports                            |
 * | view, download (report)    | view_others_imports, or view_imports and owning the import     |
 * | viewOthers                 | view_others_imports                                            |
 * | update (map, preview, run) | owning the import, with view_imports or view_others_imports    |
 * | cancel, delete             | delete_others_imports, or delete_imports and owning the import |
 *
 * Which importers a user may run is up to each importer's authorize().
 *
 * Override it with `config('permissions.policies')` or `Gate::policy()` in the app.
 */
class ImportPolicy extends ResourcePolicy
{
    protected string $resource = 'imports';

    protected bool $owned = true;

    public function viewAny(Model $user): bool
    {
        return $this->allows($user, $this->permission('view'), $this->permission('view_others'));
    }

    public function viewOthers(Model $user): bool
    {
        return $this->allows($user, $this->permission('view_others'));
    }

    public function create(Model $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(Model $user, Model $import): bool
    {
        if ($this->allows($user, $this->permission('view_others'))) {
            return true;
        }

        return $this->allows($user, $this->permission('view')) && $this->isOwner($user, $import);
    }

    public function download(Model $user, Model $import): bool
    {
        return $this->view($user, $import);
    }

    public function cancel(Model $user, Model $import): bool
    {
        return $this->delete($user, $import);
    }

    public function update(Model $user, Model $import): bool
    {
        return $this->viewAny($user) && $this->isOwner($user, $import);
    }

    public function forceDelete(Model $user, Model $import): bool
    {
        return false;
    }

    public function restore(Model $user, Model $import): bool
    {
        return false;
    }

    public function viewTrash(Model $user): bool
    {
        return false;
    }

    protected function isOwner(Model $user, Model $model): bool
    {
        return $model instanceof Import && $model->isOwnedBy($user);
    }
}
