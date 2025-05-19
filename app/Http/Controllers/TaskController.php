<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TaskCompleted;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('task.index', [
            'tasks' => Task::latest()->filter(['search', 'searchbody'])->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('task.create', [
            'users' => User::latest()->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $attributes = $this->validateTask($request);
        $attributes['taskcreator_id'] =  Auth::user()->id;
        $attributes['completed'] = 0;
        $attributes['slug'] = Str::slug($request->title);
        $task = Task::create($attributes);

        // Send notification with custom message
        $customMessage = "Tu as une nouvelle tâche : " . $task->title;
        $this->notifyUser($task->assigneduser_id, $customMessage, $task->id);

        return redirect('/')->with('success', 'Task updated and assigned user notified by email');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return view('task.show', [
            'task' => Task::find($id)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('task.edit', [
            'task' => Task::find($id),
            'users' => User::latest()->get()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $attributes = $this->validateTask($request);
        $task =  Task::find($id);
        $attributes['taskcreator_id'] = Auth::user()->id;
        $attributes['completed'] = 0;
        $attributes['slug'] = Str::slug($request->title);
        $task->update($attributes);

        // Send notification with custom message
        $customMessage = "Tu as une nouvelle tâche : " . $task->title;
        $this->notifyUser($task->assigneduser_id, $customMessage, $task->id);

        return redirect('/task')->with('success', 'Task updated and assigned user notified by email');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $task = Task::find($id);
        $task->delete();
        return redirect('/task')->with('success', 'Task Deleted');
    }

    public function validateTask(Request $request)
    {
        $attributes = $request->validate([
            'title' => 'required',
            'due' => 'required',
            'description' => 'required',
            'assigneduser_id' => ['required', Rule::exists('users', 'id')]
        ]);

        return $attributes;
    }

    public function completed($id)
    {
        $task = Task::find($id);
        $task->completed = 1;
        $task->update();
        $users = User::where('id', $task->assigneduser_id )
                        ->orWhere('id',$task->taskcreator_id)
                        ->get();

        // Custom message for task completion notification
        $customMessage = "La tâche '{$task->title}' a été marquée comme terminée.";
        Notification::send($users, new TaskCompleted($task, "La tâche '{$task->title}' a été complétée !"));

        return redirect('/task')->with('success', 'Task marked completed');
    }

    public function notifyUser($assignedUserId, $customMessage = null, $taskId = null)
    {
        if ($taskId) {
            // If a task ID is provided, use that specific task
            $task = Task::find($taskId);
        } else {
            // Otherwise, find the most recent task assigned to this user
            $task = Task::where('assigneduser_id', $assignedUserId)->latest()->first();
        }

        $user = User::find($assignedUserId);

        if (!$task || !$user) {
            return back()->with('error', 'Task or user not found');
        }

        // If a custom message is provided, use it, otherwise use the default
        Notification::send($user, new TaskAssigned($task, $customMessage));

        return back()->with('success', 'Task notification email has been sent to the assigned user');
    }

}
