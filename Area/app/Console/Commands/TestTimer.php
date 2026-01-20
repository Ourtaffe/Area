<?php

namespace App\Console\Commands;

use App\Services\ServiceFactory;
use Illuminate\Console\Command;

class TestTimer extends Command
{
    protected $signature = 'timer:test 
                           {action : Action to test}
                           {--minutes= : Minutes interval for every_x_minutes}
                           {--time= : Specific time for daily}
                           {--day= : Day for weekday}';
    
    protected $description = 'Test Timer service';

    public function handle()
    {
        $action = $this->argument('action');
        
        $this->info("⏰ Testing Timer Service: {$action}");
        
        $service = ServiceFactory::create('Timer');
        
        if (!$service) {
            $this->error('TimerService not found');
            return 1;
        }
        
        // Préparer les paramètres selon l'action
        $params = [];
        
        switch ($action) {
            case 'timer_every_x_minutes':
                $params['minutes'] = $this->option('minutes') ?? 5;
                break;
                
            case 'timer_every_day':
                $params['time'] = $this->option('time') ?? '09:00';
                break;
                
            case 'timer_weekday':
                $params['day'] = $this->option('day') ?? 'monday';
                $params['time'] = '09:00';
                break;
                
            case 'timer_specific_time':
                $params['datetime'] = now()->addMinutes(2)->format('Y-m-d H:i');
                break;
        }
        
        $this->table(
            ['Paramètre', 'Valeur'],
            array_map(fn($k, $v) => [$k, $v], array_keys($params), $params)
        );
        
        // Tester plusieurs fois pour simuler le temps
        $this->info("\n🧪 Testing trigger...");
        
        for ($i = 0; $i < 3; $i++) {
            $result = $service->checkAction($action, $params, null, 1);
            
            if ($result === false) {
                $this->line("Test {$i}: ⏭️ Not triggered");
            } else {
                $this->info("Test {$i}: ✅ TRIGGERED!");
                $this->table(
                    ['Field', 'Value'],
                    array_map(fn($k, $v) => [$k, $v], 
                        array_keys($result['data']), 
                        array_values($result['data'])
                    )
                );
            }
            
            if ($i < 2) {
                sleep(1); // Petite pause
            }
        }
        
        $this->info("\n🎯 TimerService ready!");
        return 0;
    }
}
