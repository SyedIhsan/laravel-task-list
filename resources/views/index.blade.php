@extends('layouts.app')

@section('title', 'The list of Tasks')

@section('content')
    <nav class="mb-4">
        <a href="{{ route('tasks.create') }}" class="link">
            Add Task
        </a>
    </nav>

    @forelse ($tasks as $task)
        <div class="mb-2">
            <a href="{{ route('tasks.show', $task) }}" @class(['line-through' => $task->completed])>
                {{ $task->title }}
            </a>
        </div>
    @empty
        <div>There are no tasks!</div>
    @endforelse

    @if ($tasks->count())
        <nav class="mt-4">
            {{ $tasks->links() }}
        </nav>
    @endif
@endsection
