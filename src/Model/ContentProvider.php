<?php

/*
 * This file is part of the some package.
 * (c) Jakub Janata <jakubjanata@gmail.com>
 * For the full copyright and license information, please view the LICENSE file.
 */

declare(strict_types = 1);

namespace Pehapkari\InlineEditable\Model;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * CACHING LEVELS
 * =========================
 * 1.) level 1 - php array
 * 2.) level 2 - cache
 * 3.) level 3 - persistent storage
 * =========================
 *
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
class ContentProvider
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @var PersistenceLayerInterface
     */
    protected $persistenceLayer;

    /**
     * Structure:
     * - namespace.locale:
     *      - name: content
     *      - name2: content2
     * - namespace.locale2:
     *      - name: content
     *      ...
     *
     * @var array
     */
    protected $loadedData;

    /**
     * @param array $config
     * @param CacheItemPoolInterface $cache
     * @param PersistenceLayerInterface $persistenceLayer
     */
    public function __construct(
        array $config,
        CacheItemPoolInterface $cache,
        PersistenceLayerInterface $persistenceLayer
    ) {
        $this->config = $config;
        $this->cache = $cache;
        $this->persistenceLayer = $persistenceLayer;
    }

    /**
     * L1 - PHP ARRAY LAYER
     *
     * @param string $namespace
     * @param string $locale
     * @param string $name
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getContent(string $namespace, string $locale, string $name): string
    {
        $namespaceKey = self::getNamespaceKey($namespace, $locale);

        // L1 read
        $content = $this->loadedData[$namespaceKey][$name] ?? false;

        if (is_string($content)) {
            // L1 hit
            return $content;
        }

        // L2 read + L1 write
        $this->loadedData[$namespaceKey] = $this->loadedData[$namespaceKey] ?? $this->loadNamespaceFromCache($namespace, $locale);

        $fallbackLocale = $this->config['fallback'] ?? '';
        $content = $this->loadedData[$namespaceKey][$name] ?? false;

        if (is_string($content)) {
            return $content;
        } elseif ($locale === $fallbackLocale) {
            return '';
        }

        return $this->getContent($namespace, $fallbackLocale, $name);
    }

    /**
     * @param string $namespace
     * @param string $locale
     * @param string $name
     * @param string $content
     *
     * @throws InvalidArgumentException
     */
    public function saveContent(string $namespace, string $locale, string $name, string $content)
    {
        $namespaceKey = self::getNamespaceKey($namespace, $locale);

        // L1 + L2 clear => L3 write
        unset($this->loadedData[$namespaceKey]);

        $this->cache->deleteItem($namespaceKey);
        $this->persistenceLayer->saveContent($namespace, $name, $locale, $content);
    }

    /**
     * @param string $namespace
     * @param string $locale
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function loadNamespaceFromCache(string $namespace, string $locale): array
    {
        $nKey = self::getNamespaceKey($namespace, $locale);

        // L2 read
        $cacheItem = $this->cache->getItem($nKey);

        if ($cacheItem->isHit()) {
            // L2 hit
            return $cacheItem->get();
        }

        // L3 read
        $data = $this->persistenceLayer->getNamespaceContent($namespace, $locale);

        // L2 write
        $this->cache->save($cacheItem->set($data));

        return $data;
    }

    /**
     * @param string $namespace
     * @param string $locale
     *
     * @return string
     */
    public static function getNamespaceKey(string $namespace, string $locale): string
    {
        return '__inline_prefix_' . $namespace . '.' . $locale;
    }
}
