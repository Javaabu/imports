---
title: Permissions and policies
sidebar_position: 2
---

# Permissions

The package registers these permissions with [javaabu/permissions](https://github.com/Javaabu/permissions), in the `imports` group (labelled "Imports"), for every guard in `config('permissions.guards')`:

| Permission | Description | Allows |
| --- | --- | --- |
| `view_imports` | View and start own imports | Uploading, mapping, previewing, running and following your own imports, and downloading their failed rows |
| `view_others_imports` | View other users' imports | Seeing everyone's imports and downloading their failed rows |
| `delete_imports` | Cancel and delete own imports | Cancelling and deleting your own imports |
| `delete_others_imports` | Cancel and delete other users' imports | The same for everyone's imports |

Run `php artisan permissions:sync` after installing or updating the package. Give them to roles in `config/permissions.php` or from the roles page, for example:

```php
// config/permissions.php
'roles' => [
    'super_admin' => ['*'],
    'staff' => ['view_imports', 'delete_imports', 'edit_products'],
],
```

To register your own permissions instead, set `imports.register_permissions` to `false` and register a policy for the Import model yourself.

# The policy

`Javaabu\Imports\Policies\ImportPolicy` extends javaabu/permissions' `ResourcePolicy`:

| Ability | Needs |
| --- | --- |
| `viewAny`, `create` | `view_imports` or `view_others_imports` |
| `viewOthers` | `view_others_imports` |
| `view`, `download` (the failure report) | `view_others_imports`, or `view_imports` and owning the import |
| `update` (map, preview, run, try again) | Owning the import, and `view_imports` or `view_others_imports` |
| `cancel`, `delete` | `delete_others_imports`, or `delete_imports` and owning the import |
| `restore`, `forceDelete`, `viewTrash` | Never |

Owning means the import's `user` morph is the user (`$import->isOwnedBy($user)`). Only the person who uploaded a file maps and runs it.

Running an importer also needs its own `authorize($user)` to pass, when uploading and when running. For a `ModelImporter` that's `create` on its model, so with javaabu/permissions' `ResourcePolicy` it's `edit_products` for products. Override it to use a permission of its own:

```php
public function authorize(?Model $user): bool
{
    return (bool) $user?->can('import_products');
}
```

Importers the user may not run are left out of the upload form and the API's `meta.available`.

# Overriding the policy

The app wins over the package. Either:

```php
// config/permissions.php
'policies' => [
    \Javaabu\Imports\Models\Import::class => \App\Policies\ImportPolicy::class,
],
```

or, in any service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::policy(\Javaabu\Imports\Models\Import::class, \App\Policies\ImportPolicy::class);
```

Extend the package policy to change one ability:

```php
namespace App\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Imports\Policies\ImportPolicy as BaseImportPolicy;

class ImportPolicy extends BaseImportPolicy
{
    public function cancel(Model $user, Model $import): bool
    {
        return $this->view($user, $import);
    }
}
```

`imports.policy` sets the default policy the package registers; prefer the two options above in apps.

# Multiple guards and user models

Nothing assumes the `web` guard or `App\Models\User`:

- Imports belong to any model through the `user` morph. `queue($file, $admin)` and the routes record whoever is logged in on the route's guard.
- Register the routes once per guard, each with its own prefix and name. Each route group only answers to its guard's users.
- Permissions are synced for each guard in `config('permissions.guards')`, so `view_imports` on `web_admin` is separate from `view_imports` on `web`.
- A user with `view_others_imports` sees every import in the table, whichever guard started it.

```php
Route::middleware('web')->group(function () {
    Route::imports(); // users on the default guard

    Route::imports(['guard' => 'web_admin', 'prefix' => 'admin/imports', 'name' => 'admin.imports']);
});
```

User models need javaabu/permissions' `HasRoles` trait, and `Notifiable` for notifications. If their keys aren't integers, set `imports.user_key_type` before migrating.
