<?php

namespace App\Console\Commands;

use App\Domain\Platform\Actions\CreatePlatformUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

class CreatePlatformUserCommand extends Command
{
    protected $signature = 'platform:create-user {name : Full name} {email : Sign-in email}';

    protected $description = 'Create a Super Admin; they set up two-step at first sign-in';

    public function handle(CreatePlatformUser $create): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        if (! is_string($name) || ! is_string($email)) {
            $this->error('Name and email must be text.');

            return self::FAILURE;
        }

        $password = password('Password (at least 12 characters)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:landlord.platform_users,email'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = $create->handle($name, $email, $password);

        $this->info("Created Super Admin {$user->email}. Sign in at http://".config('shiksha.platform_host').'/sign-in');

        return self::SUCCESS;
    }
}
