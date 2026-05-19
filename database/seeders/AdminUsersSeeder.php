<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminUsersSeeder extends Seeder
{
  public function run(): void
  {
    DB::transaction(function () {
      $this->seedUser(
        name: 'Super Admin',
        email: 'superadmin@example.com',
        role: 'superadmin',
      );

      $this->seedUser(
        name: 'Admin VIP',
        email: 'adminvip@example.com',
        role: 'adminvip',
      );
    });
  }

  private function seedUser(string $name, string $email, string $role): void
  {
    $user = User::firstOrNew(['email' => $email]);

    $user->name = $name;
    $user->role = $role;
    $user->email_verified_at ??= now();

    if (! $user->exists) {
      $user->password = 'password';
    }

    $user->save();

    $team = $user->personalTeam();

    if (! $team) {
      $team = Team::create([
        'name' => "{$name}'s Team",
        'is_personal' => true,
      ]);

      $team->members()->attach($user, [
        'role' => TeamRole::Owner->value,
      ]);
    } else {
      $membership = $team->memberships()->where('user_id', $user->id)->first();

      if (! $membership) {
        $team->members()->attach($user, [
          'role' => TeamRole::Owner->value,
        ]);
      } else {
        $membership->role = TeamRole::Owner;
        $membership->save();
      }
    }

    $user->switchTeam($team);
  }
}
