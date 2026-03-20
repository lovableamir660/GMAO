<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    public function view(User $user, Equipment $equipment): bool
    {
        return $user->current_site_id === $equipment->site_id;
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->current_site_id === $equipment->site_id;
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $user->current_site_id === $equipment->site_id;
    }
}
