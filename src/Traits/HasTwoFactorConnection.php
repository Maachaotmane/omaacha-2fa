<?php

namespace Omaacha\TwoFactor\Traits;

trait HasTwoFactorConnection
{
    public function initializeHasTwoFactorConnection(): void
    {
        if ($this->connection === null) {
            $connection = config('two-factor.database_connection')
                ?? config('tenancy.database.central_connection');

            if ($connection) {
                $this->setConnection($connection);
            }
        }
    }

    public function getConnectionName()
    {
        return $this->connection
            ?? config('two-factor.database_connection')
            ?? config('tenancy.database.central_connection')
            ?? parent::getConnectionName();
    }
}
