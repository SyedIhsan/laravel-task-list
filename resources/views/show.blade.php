@extends('layouts.app')

@section('title', $task->title)

@section('content')
    <div class="mb-4">
        <a href="{{ route('tasks.index') }}" class="link">
            &#8592; Back
        </a>
    </div>

    <p class="mb-4 text-slate-700">{{ $task->description }}</p>

    @if ($task->long_description)
        <p class="mb-4 text-slate-700">{{ $task->long_description }}</p>
    @endif

    <p @class(['font-medium', $task->completed ? 'text-green-500' : 'text-red-500'])>
        {{ $task->completed ? 'Completed' : 'In progress' }}
    </p>

    <p class="mb-4 text-sm text-slate-500">Created {{ $task->created_at->diffForHumans() }} &#9679; Updated {{ $task->updated_at->diffForHumans() }}</p>

    <div class="flex gap-2">
        <a href="{{ route('tasks.edit', $task) }}" class="btn">
            Edit
        </a>

        <form action="{{ route('tasks.toggle', $task) }}" method="POST">
            @csrf
            @method('PUT')
            <button type="submit" class="btn">
                Mark as {{ $task->completed ? 'not completed' : 'completed' }}
            </button>
        </form>

        <form action="{{ route('tasks.destroy', $task) }}" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="delete-btn">Delete</button>
        </form>
    </div>
@endsection
