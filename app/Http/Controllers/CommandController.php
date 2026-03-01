<?php

namespace App\Http\Controllers;

use App\Models\CommandTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommandController extends Controller
{
    public function builder()
    {
        return view('command_builder_permissions');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'action_key' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ui_type' => ['nullable', 'string', 'max:100'],
            'command_type' => ['nullable', 'string'],
            'script_name' => ['nullable', 'string', 'max:255', 'required_if:command_type,custom'],
            'script_code' => ['nullable', 'string', 'max:65535', 'required_if:command_type,custom'],
        ]);

        $commandType = strtolower(trim((string) ($data['command_type'] ?? '')));
        $scriptName = trim((string) ($data['script_name'] ?? ''));
        $scriptCode = trim((string) ($data['script_code'] ?? ''));

        if ($commandType === 'custom' && $scriptName !== '') {
            $scriptSlug = Str::slug($scriptName, '_');
            if ($scriptSlug === '') {
                $scriptSlug = 'custom_' . now()->format('YmdHis');
            }
            $actionKey = 'custom_command_' . $scriptSlug;
        } else {
            $actionKey = $data['action_key'] ?? Str::slug($data['name'], '_');
        }

        $template = CommandTemplate::updateOrCreate(
            ['action_key' => $actionKey],
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'ui_type' => $data['ui_type'] ?? 'button',
                'script_name' => $commandType === 'custom' ? $scriptName : null,
                'script_code' => $commandType === 'custom' ? $scriptCode : null,
                'active' => true,
                'created_by' => $request->session()->get('auth.user_id'),
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'commands.store',
                'command' => $template,
            ]);
        }

        return back()->with('status', "Command {$template->name} saved.");
    }

    public function discard(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'commands.discard',
            ]);
        }

        return back()->with('status', 'Draft discarded.');
    }

    public function preview(Request $request)
    {
        $payload = $request->only(['name', 'action_key', 'description', 'ui_type', 'command_type', 'script_name', 'script_code']);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'commands.preview',
                'payload' => $payload,
            ]);
        }

        return back()->with('status', 'Preview generated.')->with('previewPayload', $payload);
    }

    public function deploy(Request $request)
    {
        $actionKey = $request->input('action_key');
        $templateId = $request->input('command_id');

        $query = CommandTemplate::query();
        if ($templateId) {
            $query->where('id', $templateId);
        } elseif ($actionKey) {
            $query->where('action_key', $actionKey);
        }

        $template = $query->first();
        if ($template) {
            $template->update(['active' => true]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'action' => 'commands.deploy',
                'command' => $template,
            ]);
        }

        return back()->with('status', $template ? "Command {$template->name} deployed." : 'Command deployed.');
    }
}
