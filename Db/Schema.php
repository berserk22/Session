<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\Session\Db;

use DI\DependencyException;
use DI\NotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Modules\Database\Migration;

class Schema extends Migration {

    /**
     * @return void
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function create(): void{
        if (!$this->schema()->hasTable('session')) {
            $this->schema()->create('session', function(Blueprint $table){
                $table->engine = 'InnoDB';
                $table->string('id');
                $table->integer('user_id')->default(0); //->unsigned();
                $table->text('data');
                $table->integer('timestamp');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }
    }

    /**
     * @return void
     */
    public function delete(): void{}
}
