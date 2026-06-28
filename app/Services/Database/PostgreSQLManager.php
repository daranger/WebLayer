<?php

namespace App\Services;

use App\Contracts\DatabaseManagerInterface;
use PDO;

class PostgreSQLManager implements DatabaseManagerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function validateName(string $name): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_]{1,63}$/', $name);
    }

    public function createDatabase(string $name): bool
    {
        if (!$this->validateName($name)) return false;

        // В Postgres имена баз экранируются двойными кавычками
        return $this->pdo->exec("CREATE DATABASE \"{$name}\" ENCODING 'UTF8'") !== false;
    }

    public function createUser(string $username, string $password): bool
    {
        if (!$this->validateName($username)) return false;

        $escapedPassword = $this->pdo->quote($password);
        return $this->pdo->exec("CREATE USER \"{$username}\" WITH PASSWORD {$escapedPassword}") !== false;
    }

    public function grantPrivileges(string $database, string $username): bool
    {
        if (!$this->validateName($database) || !$this->validateName($username)) return false;

        // В Postgres права раздаются на саму базу и отдельно на схему public внутри неё
        $res1 = $this->pdo->exec("GRANT ALL PRIVILEGES ON DATABASE \"{$database}\" TO \"{$username}\"");

        // Обычно требуется переключиться на саму ДБ для раздачи прав на схемы,
        // но базовый синтаксис выглядит так:
        return $res1 !== false;
    }

    public function deleteDatabase(string $name): bool
    {
        if (!$this->validateName($name)) return false;

        return $this->pdo->exec("DROP DATABASE \"{$name}\"") !== false;
    }

    public function deleteUser(string $username): bool
    {
        if (!$this->validateName($username)) return false;

        return $this->pdo->exec("DROP USER \"{$username}\"") !== false;
    }
}