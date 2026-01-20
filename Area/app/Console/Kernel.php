<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Les commandes Artisan fournies par votre application
     */
    protected $commands = [
        \App\Console\Commands\CheckAreaHooks::class,
        \App\Console\Commands\TestNewsAPI::class,
        \App\Console\Commands\TestHackerNews::class,
        \App\Console\Commands\DebugNewsAPI::class,
        \App\Console\Commands\TestAreaCreation::class,
        \App\Console\Commands\ConnectGitHub::class, // <-- CORRIGÉ
        \App\Console\Commands\TestWeather::class,
        \App\Console\Commands\TestTwitch::class,    // <-- AJOUTER
        \App\Console\Commands\TestTimer::class,     // <-- AJOUTER
        \App\Console\Commands\TwitchDebug::class,
        \App\Services\YouTubeService::class,    // <-- AJOUTER
        // Ajoute ici tes autres commandes
    ];

    /**
     * Définir le planificateur de commandes de l'application
     */
    protected function schedule(Schedule $schedule)
    {
        // Vérifie les hooks toutes les minutes
        $schedule->command('area:check-hooks')->everyMinute();
        
        // Nettoyage des anciens logs (optionnel)
        $schedule->command('model:prune')->daily();
    }

    /**
     * Enregistrer les commandes pour l'application
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}