<?php

namespace App\Services\Database;

use App\Contracts\DatabaseManagerInterface;
use PDO;
use Exception;

class MySQLManager implements DatabaseManagerInterface
{
    private PDO $pdo;

    // Передаем PDO-подключение с правами root к MySQL
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Валидация имён БД и пользователей (разрешены только латиница, цифры и подчеркивание)
     */
    private function validateName(string $name): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_]{1,64}$/', $name);
    }

    public function createDatabase(string $name): bool
    {
        if (!$this->validateName($name)) return false;

        // Имена баз экранируем обратными кавычками
        return $this->pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") !== false;
    }

    public function createUser(string $username, string $password): bool
    {
        if (!$this->validateName($username)) return false;

        // Для пароля используем штатное экранирование строки в PDO
        $escapedPassword = $this->pdo->quote($password);

        return $this->pdo->exec("CREATE USER '{$username}'@'localhost' IDENTIFIED BY {$escapedPassword}") !== false;
    }

    public function grantPrivileges(string $database, string $username): bool
    {
        if (!$this->validateName($database) || !$this->validateName($username)) return false;

        return $this->pdo->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'localhost'") !== false;
    }

    public function deleteDatabase(string $name): bool
    {
        if (!$this->validateName($name)) return false;

        return $this->pdo->exec("DROP DATABASE `{$name}`") !== false;
    }

    public function deleteUser(string $username): bool
    {
        if (!$this->validateName($username)) return false;

        return $this->pdo->exec("DROP USER '{$username}'@'localhost'") !== false;
    }
}