<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleUserRegistered implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        Log::info("OrderService: Menerima event user baru dari Python", $this->data);

        // Jika event-nya adalah user.registered, simpan/sync ke DB lokal OrderService
        if (isset($this->data['event']) && $this->data['event'] === 'user.registered') {
            $userData = $this->data['data'];
            
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => 'external_user', // Password tidak diperlukan di sini karena auth di UserService
                ]
            );
            
            Log::info("OrderService: Berhasil sinkronisasi user: " . $userData['email']);
        }
    }
}
