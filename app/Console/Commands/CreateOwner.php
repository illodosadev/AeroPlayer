<?php

namespace App\Console\Commands;

use App\Application\Owner\Commands\CreateOwnerAccount;
use App\Application\Owner\Commands\ReplaceOwnerAccount;
use App\Application\Owner\Queries\GetOwnerAccount;
use App\Bus\CommandBus;
use App\Bus\QueryBus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateOwner extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-owner';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or replace the single owner account (interactive only)';

    /**
     * Execute the console command.
     */
    public function handle(CommandBus $commands, QueryBus $queries): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('This command needs an interactive terminal.');

            return self::FAILURE;
        }

        $owner = $queries->ask(new GetOwnerAccount);

        if ($owner && ! $this->confirm("An owner account already exists ({$owner->email}). Replace its email and password?")) {
            $this->info('Nothing changed.');

            return self::FAILURE;
        }

        $email = $this->askEmail();
        $password = $this->askPassword();

        if ($owner) {
            $commands->dispatch(new ReplaceOwnerAccount($email, $password));
            $this->info("Owner account updated for {$email}.");
        } else {
            $commands->dispatch(new CreateOwnerAccount($email, $password));
            $this->info("Owner account created for {$email}.");
        }

        return self::SUCCESS;
    }

    /**
     * Ask for the email until it is valid. It is stored in lowercase.
     */
    private function askEmail(): string
    {
        while (true) {
            $email = Str::lower(trim((string) $this->ask('Email')));

            $validator = Validator::make(['email' => $email], [
                'email' => ['required', 'email'],
            ]);

            if (! $validator->fails()) {
                return $email;
            }

            $this->error($validator->errors()->first('email'));
        }
    }

    /**
     * Ask for the password and its confirmation until both are valid.
     */
    private function askPassword(): string
    {
        $rules = Password::min(12)->mixedCase()->numbers()->symbols();

        while (true) {
            $password = (string) $this->secret('Password');

            $validator = Validator::make(['password' => $password], [
                'password' => ['required', $rules],
            ]);

            if ($validator->fails()) {
                $this->error($validator->errors()->first('password'));

                continue;
            }

            $confirmation = (string) $this->secret('Confirm password');

            if ($confirmation !== $password) {
                $this->error('The password field confirmation does not match.');

                continue;
            }

            return $password;
        }
    }
}
