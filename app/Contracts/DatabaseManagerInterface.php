<?php

namespace App\Contracts;

interface DatabaseManagerInterface
{
    public function createDatabase(string $name): bool;

    public function createUser(string $username, string $password): bool;

    public function grantPrivileges(string $database, string $username): bool;

    public function deleteDatabase(string $name): bool;

    public function deleteUser(string $username): bool;
}