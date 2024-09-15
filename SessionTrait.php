<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Session;

use Core\Traits\App;
use DI\DependencyException;
use DI\NotFoundException;
use Modules\Session\Db\Models\Session;
use Modules\Statistic\Manager\StatManager;
use Modules\Statistic\Manager\StatModel;

trait SessionTrait {

    use App;

    /**
     * @var string
     */
    private string $session = "Session\Session";

    /**
     * @return Session|string
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getSessionEntity(): Session|string {
        if (!$this->getContainer()->has($this->session)){
            $this->getContainer()->set($this->session, function(){
                return 'Modules\Session\Db\Models\Session';
            });
        }
        return $this->getContainer()->get($this->session);
    }

    /**
     * @return StatManager
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getStatisticManager(): StatManager {
        return $this->getContainer()->get('Statistic\Manager');
    }

    /**
     * @return StatModel
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getStatisticModel(): StatModel {
        return $this->getContainer()->get('Statistic\Model');
    }
}
