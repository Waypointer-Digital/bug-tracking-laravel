<?php

namespace Kanbino\BugTracking\Context;

class UserContext
{
    public static function capture(): ?array
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'email' => $user->email ?? null,
            'name' => $user->name ?? null,
            'ip' => request()->ip(),
        ];
    }
}
