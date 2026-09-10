# Laravel Task List

A learning repository. I'm using it to teach myself **Laravel**, and this is my first project built with it — a simple task list app where you can create, read, update, delete and complete tasks.

The goal here isn't a polished product. It's to touch every core piece of the framework at least once and understand *why* it works the way it does.

**Stack:** Laravel 12 · PHP 8.2 · MariaDB (Docker) · Blade · Tailwind CSS v4 · Alpine.js · Vite

---

## What I've learned so far

Roughly in the order I built things.

### 1. Environment & configuration (`.env`)

Laravel reads its configuration from a `.env` file at the project root, which stays out of version control. `.env.example` is the committed template. This is where the app name, URL, app key and database credentials live, and it's how the same codebase runs against different databases in development and production without touching any code.

### 2. Running the database with Docker

Instead of installing a database directly on my machine, `docker-compose.yml` spins up two containers:

| Service | Image | Port | What it does |
| --- | --- | --- | --- |
| `mysql` | `mariadb:10.8.3` | `3306` | The database the app talks to |
| `adminer` | `adminer` | `8080` | Browser-based database management tool |

Adminer at <http://localhost:8080> lets me inspect tables, run queries and confirm that what I *think* Eloquent is doing is what it's *actually* doing. Tearing the whole database down and starting fresh is one command.

### 3. Database schema & migrations

Migrations are version control for the database schema. Each file in `database/migrations/` has an `up()` that applies a change and a `down()` that reverses it, so the schema can be rebuilt from scratch on any machine.

`2026_06_18_102743_create_tasks_table.php` defines the `tasks` table using the `Schema` builder and a `Blueprint`:

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description');
    $table->text('long_description')->nullable();
    $table->boolean('completed')->default(false);
    $table->timestamps();
});
```

Things this taught me: choosing column types deliberately (`string` vs `text`), what `nullable()` and `default()` mean at the database level, and that `timestamps()` is what gives every record its `created_at` / `updated_at` for free.

### 4. Models and the Eloquent ORM

`App\Models\Task` extends `Illuminate\Database\Eloquent\Model`. Eloquent is Laravel's ORM — it maps the `tasks` table to a `Task` class so I write PHP instead of SQL:

```php
Task::orderBy('created_at', 'asc')->get();
Task::create($request->validated());
$task->update($request->validated());
$task->delete();
```

Key ideas: Laravel infers the table name from the class name by convention (`Task` → `tasks`), and `$task->created_at` comes back as a `Carbon` instance, which is why `diffForHumans()` works in the views without any extra date handling.

### 5. Mass assignment and `$fillable`

Passing a whole array of user input into `create()` or `update()` at once is called **mass assignment**, and it's convenient enough to be dangerous. If a request body carried a field I never intended to expose, Eloquent would happily write it to the database.

`$fillable` is the allowlist that closes that hole:

```php
protected $fillable = ['title', 'description', 'long_description'];
```

Only those three columns can be set in bulk. Note that `completed` is deliberately *not* in the list — it's a state the app controls, not something a form should be able to set directly. Anything outside the allowlist is silently dropped, and Laravel throws if no allowlist is defined at all. This is a security boundary, not a convenience setting.

### 6. Factories and seeders

Building test data by hand is slow. `TaskFactory` uses Faker to describe what a *plausible* task looks like, and `DatabaseSeeder` uses it to generate a batch:

```php
Task::factory(20)->create();
```

One command and the app has twenty realistic tasks to work with. This made it much easier to see how the list page behaves with real content instead of one or two hand-typed rows. Factories bypass `$fillable`, which is exactly what you want for seeding — they can set `completed` even though a form can't.

### 7. Routing

`routes/web.php` maps URLs to what should happen. This project deliberately uses **closure-based routes** rather than controllers, to keep the whole request flow visible in one file while learning:

| Method | URI | Name |
| --- | --- | --- |
| `GET` | `/tasks` | `tasks.index` |
| `GET` | `/tasks/create` | `tasks.create` |
| `POST` | `/tasks` | `tasks.store` |
| `GET` | `/tasks/{task}` | `tasks.show` |
| `GET` | `/tasks/{task}/edit` | `tasks.edit` |
| `PUT` | `/tasks/{task}` | `tasks.update` |
| `DELETE` | `/tasks/{task}` | `tasks.delete` |

What clicked here: **named routes**. Because every route has a name, the views call `route('tasks.show', $task)` instead of hardcoding `/tasks/5`. Change the URL structure later and nothing in the templates breaks. `Route::fallback()` catches anything that matches nothing.

Route order matters too — `/tasks/create` has to be declared before `/tasks/{task}`, or the wildcard would swallow it and try to find a task with the ID "create".

### 8. Route Model Binding

The piece I found most elegant. Type-hinting the closure parameter is enough:

```php
Route::get('/tasks/{task}', function (Task $task) {
    return view('show', ['task' => $task]);
})->name('tasks.show');
```

Laravel sees the `{task}` segment matches the `Task $task` parameter, looks the record up by primary key, injects the model, and returns a 404 automatically if it doesn't exist. No `Task::find($id)`, no manual not-found check. `getRouteKeyName()` can override the lookup column if I ever want to resolve by slug instead of ID.

### 9. Validation with Form Requests

Validation rules could live inline in the route, but `app/Http/Requests/TaskRequest.php` pulls them into a dedicated class instead:

```php
public function authorize(): bool
{
    return true;
}

public function rules(): array
{
    return [
        'title' => 'required|max:255',
        'description' => 'required',
        'long_description' => 'nullable',
    ];
}
```

The clever part is that just type-hinting it does everything:

```php
Route::post('/tasks', function (TaskRequest $request) {
    $task = Task::create($request->validated());
    // ...
});
```

Laravel resolves `TaskRequest` out of the service container and runs validation **before the closure body ever executes**. If it fails, the user is redirected back with the errors and the old input already flashed to the session — the closure simply never runs. If it passes, `$request->validated()` hands back only the fields that had rules, which pairs naturally with `$fillable`.

`authorize()` is the other half: return `false` and the request is rejected with a 403 before validation even starts. Both create and update share this one class, so the rules can't drift apart.

### 10. Blade templating

Blade is the templating engine. `resources/views/layouts/app.blade.php` is the shared shell; every page `@extends` it and fills in `@section('title')` and `@section('content')`, so the HTML skeleton is written once.

Directives I've used:

- `@forelse` / `@empty` — loop with a built-in "nothing here" branch
- `@isset` / `@if` — conditional rendering, used to make one `form.blade.php` serve both create and edit
- `@class` — conditional CSS classes, e.g. striking through completed tasks
- `@error` — renders the validation message for a specific field
- `{{ }}` — escapes output by default, which is XSS protection you get without thinking about it

That last one matters more than it looks. `{{ }}` runs everything through `htmlspecialchars`, so a task titled `<script>alert(1)</script>` renders as text rather than executing. Escaping is opt-*out* here, not opt-in.

### 11. CSRF protection & method spoofing

Two Blade directives that every form in this project needs, for two unrelated reasons.

**`@csrf`** guards against cross-site request forgery. Because the browser attaches session cookies to *any* request to my domain, a form on someone else's site could POST to `/tasks` and the request would arrive authenticated. So Laravel puts a per-session token in the form and rejects any POST/PUT/DELETE that doesn't carry a matching one — an attacker's page can't read that token, so the forged request fails. Leaving `@csrf` out gives you a 419 page, which is the framework telling you it worked.

**`@method('PUT')` / `@method('DELETE')`** exists because HTML forms only support `GET` and `POST` — the other verbs simply aren't available in the spec. The directive drops in a hidden `_method` field, and Laravel reads it and routes the request as if it really were a `PUT` or `DELETE`. That's what lets the routes stay properly RESTful:

```html
<form action="{{ route('tasks.delete', $task) }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>
```

### 12. Flash messages and the session

After a successful write the app redirects rather than rendering directly — that's the post/redirect/get pattern, and it stops a browser refresh from resubmitting the form. The message survives that redirect by riding in the session:

```php
return redirect()->route('tasks.show', $task)
    ->with('success', 'Task created successfully!');
```

`->with()` puts the value in the session **flashed**, meaning it lives for exactly one subsequent request and then deletes itself. The layout picks it up on the other side:

```blade
@if (session('success'))
    <div role="alert">{{ session('success') }}</div>
@endif
```

Show it once, and it's gone — no manual cleanup. Validation errors work the same way, which is why `@error` and `old()` have anything to display after a failed submit.

### 13. Styling with Tailwind CSS

Tailwind is utility-first: style with small single-purpose classes in the markup instead of writing separate CSS. Repeated patterns get pulled into component classes with `@apply` in the layout's `<style type="text/tailwindcss">` block — that's where `.btn`, `.delete-btn`, `.link` and the shared `input` / `textarea` / `label` styling come from.

### 14. Alpine.js for interactivity

Alpine adds small amounts of behaviour directly in the markup, no build step and no separate JS file. In this project it powers the dismissible success message from section 12:

```html
<div x-data="{ flash: true }">
    <div x-show="flash" role="alert">
        ...
        <svg @click="flash = false">...</svg>
    </div>
</div>
```

`x-data` declares local state, `x-show` binds visibility to it, `@click` mutates it. That's the whole thing — the right tool when a full frontend framework would be overkill.

### 15. Artisan commands

`php artisan` is Laravel's CLI and the fastest way to get things done:

```bash
php artisan serve                      # run the dev server
php artisan make:model Task -mf        # model + migration + factory in one go
php artisan make:request TaskRequest   # form request for validation
php artisan migrate                    # apply migrations
php artisan migrate:fresh --seed       # drop everything, rebuild, seed
php artisan db:seed                    # run seeders
php artisan route:list                 # every registered route
php artisan optimize:clear             # clear cached config, routes and views
php artisan tinker                     # REPL to poke at models directly
```

`route:list` and `tinker` in particular turned out to be the debugging tools I reach for most.

---

## Running it locally

```bash
# 1. Install dependencies
composer install
npm install

# 2. Set up the environment
cp .env.example .env
php artisan key:generate

# 3. Start the database and Adminer
docker compose up -d

# 4. Create the database, then build the schema and seed it
php artisan migrate --seed

# 5. Serve the app
php artisan serve
```

| What | Where |
| --- | --- |
| App | <http://localhost:8000> |
| Adminer | <http://localhost:8080> |

Database credentials are in `.env` — `laravel-task-list`, user `root`, password `root`, on `127.0.0.1:3306`.

---

## Project layout

```
app/
  Http/Requests/TaskRequest.php    validation rules for create & update
  Models/Task.php                  the Eloquent model
database/
  factories/TaskFactory.php        fake task generator
  migrations/                      schema history
  seeders/DatabaseSeeder.php       populates the database
resources/views/
  layouts/app.blade.php            shared shell, component styles, flash message
  index.blade.php                  the task list
  show.blade.php                   a single task
  form.blade.php                   create & edit, one shared form
routes/web.php                     every route, defined as closures
docker-compose.yml                 MariaDB + Adminer
```

---

## What's next

- Move the closures in `routes/web.php` into a resource controller
- Authentication, so tasks belong to a user
- Feature tests for the CRUD flow
