<?php

use App\Http\Controllers\TaskController;
use App\Http\Requests\TaskRequest;
use App\Models\Task;
use Illuminate\Support\Facades\Route;


//  Show list of task
Route::get('/', function () {
    return redirect()->route('tasks.index');
});


Route::resource('tasks', TaskController::class);


Route::put('/tasks/{task}/toggle-complete', function (Task $task) {
    $task->toggleComplete();

    return redirect()->back()
        ->with('success', 'Task status updated!');
})->name('tasks.toggle');


Route::fallback(function () {
    return 'Oops! Looks like you\'ve chose a wrong path';
});

// Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
// Add task
// Route::view('/tasks/create', 'form')->name('tasks.create');
// Edit task
// Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
// // Show task detail
// Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');


// Route::post('/tasks', function (TaskRequest $request) {
//     $task = Task::create($request->validated());

//     return redirect()->route('tasks.show', $task)
//         ->with('success', 'Task created successfully!');
// })->name('tasks.store');


// Route::put('/tasks/{task}', function (Task $task, TaskRequest $request) {
//     $task->update($request->validated());

//     return redirect()->route('tasks.show', $task)
//         ->with('success', 'Task updated successfully!');
// })->name('tasks.update');


// Route::delete('/tasks/{task}', function (Task $task) {
//     $task->delete();

//     return redirect()->route('tasks.index')
//         ->with('success', 'Task deleted successfully!');
// })->name('tasks.destroy');
