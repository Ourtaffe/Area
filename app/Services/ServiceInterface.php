<?php

namespace App\Services;

use Carbon\Carbon;

interface ServiceInterface
{
    /**
     *
     * 
     * @param string
     * @param array
     * @param Carbon|null
     * @param int|null
     * @return array|false
     */
    public function checkAction(string $actionName, array $params, ?Carbon $lastExecutedAt, ?int $userId = null): array|false;

    /**
     * 
     * 
     * @param string 
     * @param array 
     * @param array 
     * @return array 
     */
    public function executeReaction(string $reactionName, array $params, array $actionData = []): array;
}
