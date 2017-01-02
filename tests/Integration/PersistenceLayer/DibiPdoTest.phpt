<?php

namespace Pehapkari\Inline\Tests\Integration\PersistentLayer;

use Dibi\Connection;
use Pehapkari\Inline\Model\PersistenceLayer\Dibi;
use Tester\Environment;

require __DIR__ . '/BaseTest.php';

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 * @dataProvider ../../databases.ini
 */
class DibiPdoTest extends BaseTest
{
    /**
     *
     */
    protected function initPersistentLayer()
    {
        $params = Environment::loadData();
        $params['driver'] = 'pdo';

        $connection = new Connection($params);
        $this->persistentLayer = new Dibi('inline_content', $connection);
    }
}

(new DibiPdoTest)->run();