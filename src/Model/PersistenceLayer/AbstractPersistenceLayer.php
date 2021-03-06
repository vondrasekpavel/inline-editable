<?php

/*
 * This file is part of the some package.
 * (c) Jakub Janata <jakubjanata@gmail.com>
 * For the full copyright and license information, please view the LICENSE file.
 */

declare(strict_types = 1);

namespace Pehapkari\InlineEditable\Model\PersistenceLayer;

use Pehapkari\InlineEditable\Model\PersistenceLayerInterface;
use UnexpectedValueException;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
abstract class AbstractPersistenceLayer implements PersistenceLayerInterface
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    protected $driverName;

    /**
     * @param string $tableName
     */
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * @param string $sql
     * @param array $args
     *
     * @return array
     */
    abstract protected function getKeyPairResult(string $sql, array $args): array;

    /**
     * @param string $sql
     * @param array $args
     *
     * @return bool
     */
    abstract protected function updateOrInsertRecord(string $sql, array $args): bool;

    /**
     * @return string
     */
    abstract protected function getDriverName(): string;

    /**
     * @param string $namespace
     * @param string $locale
     *
     * @return array
     */
    public function getNamespaceContent(string $namespace, string $locale): array
    {
        $sql = "SELECT name, content FROM $this->tableName WHERE namespace = ? AND locale = ?";

        return $this->getKeyPairResult($sql, [$namespace, $locale]);
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param string $locale
     * @param string $content
     * @return bool
     *
     * @throws UnexpectedValueException
     */
    public function saveContent(string $namespace, string $name, string $locale, string $content): bool
    {
        $sql = "INSERT INTO $this->tableName (namespace, name, locale, content) VALUES (?, ?, ?, ?) ";

        $driver = $this->detectDbDriver();

        if ($driver === 'mysql') {
            $sql = "$sql ON DUPLICATE KEY UPDATE content = VALUES(content)";
        } elseif ($driver === 'pgsql') {
            $sql = "$sql ON CONFLICT (namespace, name, locale) DO UPDATE SET content = EXCLUDED.content";
        } else {
            throw new UnexpectedValueException("Unknown driver '$driver'");
        }

        return $this->updateOrInsertRecord($sql, [$namespace, $name, $locale, $content]);
    }

    /**
     * @return string
     */
    private function detectDbDriver(): string
    {
        if (!$this->driverName) {
            $driverName = $this->getDriverName();

            $this->driverName = '';

            if (strpos($driverName, 'mysql') !== false) {
                $this->driverName = 'mysql';
            } elseif (strpos($driverName, 'pgsql') !== false || strpos($driverName, 'postgre') !== false) {
                $this->driverName = 'pgsql';
            }
        }

        return $this->driverName;
    }
}
