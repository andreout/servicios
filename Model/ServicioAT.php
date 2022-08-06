<?php
/**
 * This file is part of Servicios plugin for FacturaScripts
 * Copyright (C) 2020-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\Servicios\Model;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base;
use FacturaScripts\Dinamic\Lib\Email\MailNotifier;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\TrabajoAT as DinTrabajoAT;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\Servicios\Lib\ServiceTool;

/**
 * Description of ServicioAT
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ServicioAT extends Base\ModelOnChangeClass
{
    use Base\ModelTrait;
    use Base\CompanyRelationTrait;

    /** @var string */
    public $asignado;

    /** @var string */
    public $codagente;

    /** @var string */
    public $codalmacen;

    /** @var string */
    public $codcliente;

    /** @var string */
    public $descripcion;

    /** @var bool */
    public $editable;

    /** @var string */
    public $fecha;

    /** @var string */
    public $hora;

    /** @var int */
    public $idestado;

    /** @var int */
    public $idmaquina;

    /** @var int */
    public $idmaquina2;

    /** @var int */
    public $idmaquina3;

    /** @var int */
    public $idmaquina4;

    /** @var int */
    public $idprioridad;

    /** @var int */
    public $idservicio;

    /** @var string */
    public $material;

    /** @var string */
    public $nick;

    /** @var double */
    public $neto;

    /** @var string */
    public $observaciones;

    /** @var string */
    public $solucion;

    /** @var string */
    protected $messageLog = 'updated-model';

    public function calculatePriceNet()
    {
        $this->neto = 0.0;
        foreach ($this->getTrabajos() as $trabajo) {
            $this->neto += $trabajo->precio * $trabajo->cantidad;
        }
        $this->save();
    }

    public function clear()
    {
        parent::clear();
        $this->fecha = date(self::DATE_STYLE);
        $this->hora = date(self::HOUR_STYLE);
        $this->neto = 0.0;

        // set default status
        foreach ($this->getAvailableStatus() as $status) {
            if ($status->predeterminado) {
                $this->idestado = $status->id;
                $this->editable = $status->editable;
                break;
            }
        }

        // set default priority
        foreach ($this->getAvailablePriority() as $priority) {
            if ($priority->predeterminado) {
                $this->idprioridad = $priority->id;
                break;
            }
        }
    }

    public function delete(): bool
    {
        foreach ($this->getTrabajos() as $trabajo) {
            if (false === $trabajo->delete()) {
                return false;
            }
        }

        return parent::delete();
    }

    /**
     * @return EstadoAT[]
     */
    public function getAvailableStatus(): array
    {
        $status = new EstadoAT();
        return $status->all([], [], 0, 0);
    }

    /**
     * @return MaquinaAT[]
     */
    public function getMachines(): array
    {
        $result = [];
        $machines = [$this->idmaquina, $this->idmaquina2, $this->idmaquina3, $this->idmaquina4];
        foreach ($machines as $code) {
            if (empty($code)) {
                continue;
            }

            $machine = new MaquinaAT();
            $machine->loadFromCode($code);
            $result[] = $machine;
        }

        return $result;
    }

    /**
     * @return EstadoAT
     */
    public function getStatus()
    {
        $status = new EstadoAT();
        $status->loadFromCode($this->idestado);
        return $status;
    }

    /**
     * @return PrioridadAT[]
     */
    public function getAvailablePriority(): array
    {
        $priority = new PrioridadAT();
        return $priority->all([], [], 0, 0);
    }

    /**
     * @return PrioridadAT
     */
    public function getPriority()
    {
        $priority = new PrioridadAT();
        $priority->loadFromCode($this->idprioridad);
        return $priority;
    }

    public function getSubject(): Cliente
    {
        $cliente = new Cliente();
        $cliente->loadFromCode($this->codcliente);
        return $cliente;
    }

    /**
     * @return DinTrabajoAT[]
     */
    public function getTrabajos(): array
    {
        $trabajo = new DinTrabajoAT();
        $where = [new DataBaseWhere('idservicio', $this->idservicio)];
        $order = ['fechainicio' => 'ASC', 'horainicio' => 'ASC'];
        return $trabajo->all($where, $order, 0, 0);
    }

    public function install(): string
    {
        // needed dependencies
        new MaquinaAT();
        new EstadoAT();
        new PrioridadAT();

        return parent::install();
    }

    public static function primaryColumn(): string
    {
        return 'idservicio';
    }

    public function primaryDescriptionColumn(): string
    {
        return 'idservicio';
    }

    public static function tableName(): string
    {
        return 'serviciosat';
    }

    public function test(): bool
    {
        $utils = $this->toolBox()->utils();
        $fields = ['descripcion', 'material', 'observaciones', 'solucion'];
        foreach ($fields as $key) {
            $this->{$key} = $utils->noHtml($this->{$key});
        }

        return parent::test();
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return $type === 'new' ? 'NewServicioAT' : parent::url($type, $list);
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    protected function onChange($field)
    {
        switch ($field) {
            case 'asignado':
                if ($this->asignado) {
                    $this->notifyAssignedUser('new-service-assignee');
                }
                break;

            case 'codagente':
                if ($this->codagente) {
                    $this->notifyAgent('new-service-agent');
                }
                break;

            case 'codcliente':
                if ($this->codcliente) {
                    $this->notifyCustomer('new-service-customer');
                }
                break;

            case 'idestado':
                return $this->onChangeIdestado();

            case 'nick':
                if ($this->nick) {
                    $this->notifyUser('new-service-user');
                }
                break;
        }

        return parent::onChange($field);
    }

    protected function onChangeIdestado(): bool
    {
        $status = $this->getStatus();

        // añadimos el cambio al log
        $this->messageLog = self::toolBox()->i18n()->trans('changed-status-to', [
            '%status%' => $status->nombre,
            '%model%' => $this->modelClassName(),
            '%key%' => $this->primaryColumnValue(),
            '%desc%' => $this->primaryDescription(),
        ]);

        // asignamos el valor de editable
        $this->editable = $status->editable;

        // si el estado tiene un asignado, lo asignamos
        if ($status->asignado) {
            $this->asignado = $status->asignado;
        }

        // enviamos las notificaciones
        if ($this->asignado && $status->notificarasignado) {
            $this->notifyAssignedUser('new-service-status');
        }
        if ($this->codagente && $status->notificaragente) {
            $this->notifyAgent('new-service-status');
        }
        if ($this->codcliente && $status->notificarcliente) {
            $this->notifyCustomer('new-service-status');
        }
        if ($this->nick && $status->notificarusuario) {
            $this->notifyUser('new-service-status');
        }
        return true;
    }

    protected function onInsert()
    {
        // enviamos notificaciones
        if ($this->asignado) {
            $this->notifyAssignedUser('new-service-assignee');
        }
        if ($this->codagente) {
            $this->notifyAgent('new-service-agent');
        }
        if ($this->codcliente) {
            $this->notifyCustomer('new-service-customer');
        }

        // añadimos entrada al log
        self::toolBox()->i18nLog(self::AUDIT_CHANNEL)->info('new-service-created', [
            '%model%' => $this->modelClassName(),
            '%key%' => $this->primaryColumnValue(),
            '%desc%' => '',
            'model-class' => $this->modelClassName(),
            'model-code' => $this->primaryColumnValue(),
            'model-data' => $this->toArray()
        ]);

        parent::onInsert();
    }

    protected function onUpdate()
    {
        // add audit log
        self::toolBox()->i18nLog(self::AUDIT_CHANNEL)->info($this->messageLog, [
            '%model%' => $this->modelClassName(),
            '%key%' => $this->primaryColumnValue(),
            '%desc%' => '',
            'model-class' => $this->modelClassName(),
            'model-code' => $this->primaryColumnValue(),
            'model-data' => $this->toArray()
        ]);

        parent::onUpdate();
    }

    protected function notifyAgent(string $notification)
    {
        $agent = new Agente();
        if (false === $agent->loadFromCode($this->codagente) || empty($agent->email)) {
            return;
        }

        MailNotifier::send($notification, $agent->email, $agent->nombre, [
            'number' => $this->idservicio,
            'customer' => $this->getSubject()->nombre,
            'author' => $this->nick,
            'status' => $this->getStatus()->nombre,
            'url' => ServiceTool::getSiteUrl() . '/EditServicioAT?code=' . $this->idservicio
        ]);
    }

    protected function notifyAssignedUser(string $notification)
    {
        $assigned = new User();
        if (false === $assigned->loadFromCode($this->asignado)) {
            return;
        }

        MailNotifier::send($notification, $assigned->email, $assigned->nick, [
            'number' => $this->idservicio,
            'customer' => $this->getSubject()->nombre,
            'author' => $this->nick,
            'status' => $this->getStatus()->nombre,
            'url' => ServiceTool::getSiteUrl() . '/EditServicioAT?code=' . $this->idservicio
        ]);
    }

    protected function notifyCustomer(string $notification)
    {
        $customer = $this->getSubject();
        if (empty($customer) || empty($customer->email)) {
            return;
        }

        MailNotifier::send($notification, $customer->email, $customer->nombre, [
            'number' => $this->idservicio,
            'customer' => $customer->nombre,
            'author' => $this->nick,
            'status' => $this->getStatus()->nombre,
            'url' => ServiceTool::getSiteUrl() . '/EditServicioAT?code=' . $this->idservicio
        ]);
    }

    protected function notifyUser(string $notification)
    {
        $user = new User();
        if (false === $user->loadFromCode($this->nick)) {
            return;
        }

        MailNotifier::send($notification, $user->email, $user->nick, [
            'number' => $this->idservicio,
            'customer' => $this->getSubject()->nombre,
            'author' => $this->nick,
            'status' => $this->getStatus()->nombre,
            'url' => ServiceTool::getSiteUrl() . '/EditServicioAT?code=' . $this->idservicio
        ]);
    }

    protected function setPreviousData(array $fields = [])
    {
        $more = ['idestado', 'asignado', 'codagente', 'codcliente', 'nick'];
        parent::setPreviousData(array_merge($more, $fields));
    }
}
