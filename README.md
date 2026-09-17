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
| `mysql` | `mariadb:10.8.3` | `3307` | The database the app talks to |
| `adminer` | `adminer` | `8081` | Browser-based database management tool |

Adminer at <http://localhost:8081> lets me inspect tables, run queries and confirm that what I *think* Eloquent is doing is what it's *actually* doing. Tearing the whole database down and starting fresh is one command.

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
Task::latest()->paginate(10);
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

`routes/web.php` maps URLs to what should happen. I started out with **closure-based routes** — one closure per URL — to keep the whole request flow visible in one file while learning. Once that flow made sense, I moved the CRUD logic into a resource controller (section 8), and now the routes file is only a few lines:

```php
Route::get('/', fn () => redirect()->route('tasks.index'));

Route::resource('tasks', TaskController::class);

Route::put('/tasks/{task}/toggle-complete', function (Task $task) {
    $task->toggleComplete();

    return redirect()->back()->with('success', 'Task status updated!');
})->name('tasks.toggle');

Route::fallback(fn () => 'Oops! Looks like you\'ve chose a wrong path');
```

That single `Route::resource()` line registers all seven CRUD routes:

| Method | URI | Name | Controller method |
| --- | --- | --- | --- |
| `GET` | `/tasks` | `tasks.index` | `index` |
| `GET` | `/tasks/create` | `tasks.create` | `create` |
| `POST` | `/tasks` | `tasks.store` | `store` |
| `GET` | `/tasks/{task}` | `tasks.show` | `show` |
| `GET` | `/tasks/{task}/edit` | `tasks.edit` | `edit` |
| `PUT/PATCH` | `/tasks/{task}` | `tasks.update` | `update` |
| `DELETE` | `/tasks/{task}` | `tasks.destroy` | `destroy` |

Plus one custom route that isn't part of standard CRUD: `PUT /tasks/{task}/toggle-complete` → `tasks.toggle`, still a closure.

What clicked here: **named routes**. Because every route has a name, the views call `route('tasks.show', $task)` instead of hardcoding `/tasks/5`. That paid off immediately during the switch to a resource controller — the URLs stayed the same, and the only template change was renaming my old `tasks.delete` route to the conventional `tasks.destroy`. `Route::fallback()` catches anything that matches nothing.

Route order matters too — `/tasks/create` has to be matched before `/tasks/{task}`, or the wildcard would swallow it and try to find a task with the ID "create". When I wrote the routes by hand I had to get that order right myself; `Route::resource()` registers them in the correct order automatically.

### 8. Resource Controllers

Closures are great for seeing everything at once, but a routes file full of logic gets long fast. A **controller** groups related request handling into one class, and a **resource controller** follows Laravel's naming convention for the seven CRUD actions. Artisan generates the skeleton:

```bash
php artisan make:controller TaskController --resource
```

`app/Http/Controllers/TaskController.php` then holds what used to be the closures, one method per action:

```php
class TaskController extends Controller
{
    public function index()
    {
        return view('index', ['tasks' => Task::latest()->paginate(10)]);
    }

    public function store(TaskRequest $request)
    {
        $task = Task::create($request->validated());

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task created successfully!');
    }

    public function update(Task $task, TaskRequest $request) { /* ... */ }

    public function destroy(Task $task) { /* ... */ }

    // create(), show(), edit() ...
}
```

Moving the code over was almost copy-paste: each closure body became a method body, with the same type-hinted parameters. Everything the closures relied on — route model binding, form request validation, flash messages — works identically in controller methods, because Laravel resolves method parameters the same way it resolves closure parameters.

What I got out of it:

- **Convention over configuration** — method names (`index`, `store`, `destroy`…) map to route names (`tasks.index`, `tasks.store`, `tasks.destroy`…) with no extra wiring.
- **A thin routes file** — `web.php` now describes *which* URLs exist; the controller describes *what* they do.
- **Room to grow** — `Route::resource(...)->only([...])` or `->except([...])` trims the set if a resource doesn't need all seven actions, and custom actions like `toggle-complete` can sit alongside it.

### 9. Route Model Binding

The piece I found most elegant. Type-hinting the parameter is enough:

```php
public function show(Task $task)
{
    return view('show', ['task' => $task]);
}
```

Laravel sees the `{task}` segment matches the `Task $task` parameter, looks the record up by primary key, injects the model, and returns a 404 automatically if it doesn't exist. No `Task::find($id)`, no manual not-found check. `getRouteKeyName()` can override the lookup column if I ever want to resolve by slug instead of ID.

### 10. Validation with Form Requests

Validation rules could live inline in the controller, but `app/Http/Requests/TaskRequest.php` pulls them into a dedicated class instead:

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
public function store(TaskRequest $request)
{
    $task = Task::create($request->validated());
    // ...
}
```

Laravel resolves `TaskRequest` out of the service container and runs validation **before the method body ever executes**. If it fails, the user is redirected back with the errors and the old input already flashed to the session — the method simply never runs. If it passes, `$request->validated()` hands back only the fields that had rules, which pairs naturally with `$fillable`.

`authorize()` is the other half: return `false` and the request is rejected with a 403 before validation even starts. Both `store()` and `update()` share this one class, so the rules can't drift apart.

### 11. Blade templating

Blade is the templating engine. `resources/views/layouts/app.blade.php` is the shared shell; every page `@extends` it and fills in `@section('title')` and `@section('content')`, so the HTML skeleton is written once.

Directives I've used:

- `@forelse` / `@empty` — loop with a built-in "nothing here" branch
- `@isset` / `@if` — conditional rendering, used to make one `form.blade.php` serve both create and edit
- `@class` — conditional CSS classes, e.g. striking through completed tasks
- `@error` — renders the validation message for a specific field
- `{{ }}` — escapes output by default, which is XSS protection you get without thinking about it

That last one matters more than it looks. `{{ }}` runs everything through `htmlspecialchars`, so a task titled `<script>alert(1)</script>` renders as text rather than executing. Escaping is opt-*out* here, not opt-in.

### 12. CSRF protection & method spoofing

Two Blade directives that every form in this project needs, for two unrelated reasons.

**`@csrf`** guards against cross-site request forgery. Because the browser attaches session cookies to *any* request to my domain, a form on someone else's site could POST to `/tasks` and the request would arrive authenticated. So Laravel puts a per-session token in the form and rejects any POST/PUT/DELETE that doesn't carry a matching one — an attacker's page can't read that token, so the forged request fails. Leaving `@csrf` out gives you a 419 page, which is the framework telling you it worked.

**`@method('PUT')` / `@method('DELETE')`** exists because HTML forms only support `GET` and `POST` — the other verbs simply aren't available in the spec. The directive drops in a hidden `_method` field, and Laravel reads it and routes the request as if it really were a `PUT` or `DELETE`. That's what lets the routes stay properly RESTful:

```html
<form action="{{ route('tasks.destroy', $task) }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>
```

### 13. Flash messages and the session

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

### 14. Styling with Tailwind CSS

Tailwind is utility-first: style with small single-purpose classes in the markup instead of writing separate CSS. Repeated patterns get pulled into component classes with `@apply` in the layout's `<style type="text/tailwindcss">` block — that's where `.btn`, `.delete-btn`, `.link` and the shared `input` / `textarea` / `label` styling come from.

### 15. Alpine.js for interactivity

Alpine adds small amounts of behaviour directly in the markup, no build step and no separate JS file. In this project it powers the dismissible success message from section 13:

```html
<div x-data="{ flash: true }">
    <div x-show="flash" role="alert">
        ...
        <svg @click="flash = false">...</svg>
    </div>
</div>
```

`x-data` declares local state, `x-show` binds visibility to it, `@click` mutates it. That's the whole thing — the right tool when a full frontend framework would be overkill.

### 16. Artisan commands

`php artisan` is Laravel's CLI and the fastest way to get things done:

```bash
php artisan serve                      # run the dev server
php artisan make:model Task -mf        # model + migration + factory in one go
php artisan make:controller TaskController --resource  # controller with all 7 CRUD methods
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
| Adminer | <http://localhost:8081> |

Database credentials are in `.env` — `laravel-task-list`, user `root`, password `root`, on `127.0.0.1:3307` (mapped to `3306` inside the container).

---

## Project layout

```
app/
  Http/Controllers/TaskController.php  resource controller for task CRUD
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
routes/web.php                     resource route + toggle-complete & fallback
docker-compose.yml                 MariaDB + Adminer
```

---

## What's next

- Authentication, so tasks belong to a user
- Feature tests for the CRUD flow
