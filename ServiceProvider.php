<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Session;

use Core\Module\Provider;
use DI\DependencyException;
use DI\NotFoundException;
use Modules\Database\MigrationCollection;
use Modules\Session\Db\Schema;

class ServiceProvider extends Provider {

    public function init(): void {
        $container=$this->getContainer();
        if (!$container->has('Session\Manager')){
            $container->set('Session\Manager', function(){
                $manager = new SessionManager($this);
                $manager->registry();
                return $manager;
            });
        }
    }

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function afterInit(): void {
        $container = $this->getContainer();
        if ($container->has('Modules\Database\ServiceProvider::Migration::Collection')) {
            /* @var $databaseMigration MigrationCollection  */
            $container->get('Modules\Database\ServiceProvider::Migration::Collection')->add(new Schema($this));
        }
    }
}
