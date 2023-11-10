<?php
/**
 * This file is part of Servicios plugin for FacturaScripts
 * Copyright (C) 2020-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Servicios\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Plugins\Servicios\Model\EstadoAT;

/**
 * Description of ListServicioAT
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListServicioAT extends ListController
{
    /** @var EstadoAT[] */
    protected $serviceStatus;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'services';
        $data['icon'] = 'fas fa-headset';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewsServices();
        $this->createViewsServicesClosed();
        $this->createViewsWorks();
        $this->createViewsMachines();
    }

    protected function createViewsMachines(string $viewName = 'ListMaquinaAT'): void
    {
        $this->addView($viewName, 'MaquinaAT', 'machines', 'fas fa-laptop-medical');
        $this->addOrderBy($viewName, ['idmaquina'], 'code', 2);
        $this->addOrderBy($viewName, ['fecha'], 'date');
        $this->addOrderBy($viewName, ['nombre'], 'name');
        $this->addOrderBy($viewName, ['referencia'], 'reference');
        $this->addSearchFields($viewName, ['descripcion', 'idmaquina', 'nombre', 'numserie', 'referencia']);

        // filtros
        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');

        $manufacturers = $this->codeModel->all('fabricantes', 'codfabricante', 'nombre');
        $this->addFilterSelect($viewName, 'codfabricante', 'manufacturer', 'codfabricante', $manufacturers);

        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'nombre');

        $agents = $this->codeModel->all('agentes', 'codagente', 'nombre');
        $this->addFilterSelect($viewName, 'codagente', 'agent', 'codagente', $agents);
    }

    protected function createViewsServices(string $viewName = 'ListServicioAT'): void
    {
        $this->addView($viewName, 'ServicioAT', 'services', 'fas fa-headset');
        $this->addOrderBy($viewName, ['fecha', 'hora'], 'date', 2);
        $this->addOrderBy($viewName, ['idprioridad'], 'priority');
        $this->addOrderBy($viewName, ['idservicio'], 'code');
        $this->addOrderBy($viewName, ['neto'], 'net');
        $this->addSearchFields($viewName, [
            'codigo', 'descripcion', 'idservicio', 'material', 'observaciones', 'solucion', 'telefono1', 'telefono2'
        ]);

        // filtros
        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');
        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'nombre');

        $priority = $this->codeModel->all('serviciosat_prioridades', 'id', 'nombre');
        $this->addFilterSelect($viewName, 'idprioridad', 'priority', 'idprioridad', $priority);
        $type = $this->codeModel->all('serviciosat_tipos', 'id', 'tipo');
        $this->addFilterSelect($viewName, 'idtipo', 'type', 'idtipo', $type);

        // obtenemos los estados editables
        $valuesWhere = [
            ['label' => $this->toolBox()->i18n()->trans('only-active'), 'where' => [new DataBaseWhere('editable', true)]],
            ['label' => '------', 'where' => [new DataBaseWhere('editable', true)]],
        ];
        foreach ($this->getServiceStatus() as $estado) {
            if ($estado->editable) {
                $valuesWhere[] = [
                    'label' => $estado->nombre,
                    'where' => [new DataBaseWhere('idestado', $estado->id)]
                ];
            }
        }
        $this->addFilterSelectWhere($viewName, 'status', $valuesWhere);

        $users = $this->codeModel->all('users', 'nick', 'nick');
        $this->addFilterSelect($viewName, 'nick', 'user', 'nick', $users);
        $this->addFilterSelect($viewName, 'asignado', 'assigned', 'asignado', $users);

        $agents = $this->codeModel->all('agentes', 'codagente', 'nombre');
        $this->addFilterSelect($viewName, 'codagente', 'agent', 'codagente', $agents);

        $this->addFilterNumber($viewName, 'netogt', 'net', 'neto', '>=');
        $this->views[$viewName]->addFilterNumber('netolt', 'net', 'neto', '<=');

        $this->setServicesColors($viewName);
    }

    protected function createViewsServicesClosed(string $viewName = 'ListServicioAT-closed'): void
    {
        $this->addView($viewName, 'ServicioAT', 'closed', 'fas fa-lock');
        $this->addOrderBy($viewName, ['fecha', 'hora'], 'date', 2);
        $this->addOrderBy($viewName, ['idprioridad'], 'priority');
        $this->addOrderBy($viewName, ['idservicio'], 'code');
        $this->addOrderBy($viewName, ['neto'], 'net');
        $this->addSearchFields($viewName, [
            'codigo', 'descripcion', 'idservicio', 'material', 'observaciones', 'solucion', 'telefono1', 'telefono2'
        ]);

        // filtros
        $this->addFilterPeriod($viewName, 'fecha', 'date', 'fecha');
        $this->addFilterAutocomplete($viewName, 'codcliente', 'customer', 'codcliente', 'clientes', 'codcliente', 'nombre');

        $priority = $this->codeModel->all('serviciosat_prioridades', 'id', 'nombre');
        $this->addFilterSelect($viewName, 'idprioridad', 'priority', 'idprioridad', $priority);
        $type = $this->codeModel->all('serviciosat_tipos', 'id', 'tipo');
        $this->addFilterSelect($viewName, 'idtipo', 'type', 'idtipo', $type);

        // obtenemos los estados no editables
        $valuesWhere = [
            ['label' => $this->toolBox()->i18n()->trans('only-closed'), 'where' => [new DataBaseWhere('editable', false)]],
            ['label' => '------', 'where' => [new DataBaseWhere('editable', false)]],
        ];
        foreach ($this->getServiceStatus() as $estado) {
            if (false === $estado->editable) {
                $valuesWhere[] = [
                    'label' => $estado->nombre,
                    'where' => [new DataBaseWhere('idestado', $estado->id)]
                ];
            }
        }
        $this->addFilterSelectWhere($viewName, 'status', $valuesWhere);

        $users = $this->codeModel->all('users', 'nick', 'nick');
        $this->addFilterSelect($viewName, 'nick', 'user', 'nick', $users);
        $this->addFilterSelect($viewName, 'asignado', 'assigned', 'asignado', $users);

        $agents = $this->codeModel->all('agentes', 'codagente', 'nombre');
        $this->addFilterSelect($viewName, 'codagente', 'agent', 'codagente', $agents);

        $this->addFilterNumber($viewName, 'netogt', 'net', 'neto', '>=');
        $this->views[$viewName]->addFilterNumber('netolt', 'net', 'neto', '<=');

        $this->setServicesColors($viewName);
    }

    protected function createViewsWorks(string $viewName = 'ListTrabajoAT'): void
    {
        $this->addView($viewName, 'TrabajoAT', 'work', 'fas fa-stethoscope');
        $this->addOrderBy($viewName, ['fechainicio', 'horainicio'], 'from-date');
        $this->addOrderBy($viewName, ['fechafin', 'horafin'], 'until-date', 2);
        $this->addOrderBy($viewName, ['idservicio', 'idtrabajo'], 'service');
        $this->addSearchFields($viewName, ['descripcion', 'observaciones', 'referencia']);

        // filtros
        $users = $this->codeModel->all('users', 'nick', 'nick');
        $this->addFilterSelect($viewName, 'nick', 'user', 'nick', $users);

        $agents = $this->codeModel->all('agentes', 'codagente', 'nombre');
        $this->addFilterSelect($viewName, 'codagente', 'agent', 'codagente', $agents);

        // desactivamos los botones nuevo y eliminar
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
    }

    protected function getServiceStatus(): array
    {
        if (empty($this->serviceStatus)) {
            $estadoModel = new EstadoAT();
            $this->serviceStatus = $estadoModel->all([], [], 0, 0);
        }

        return $this->serviceStatus;
    }

    protected function setServicesColors(string $viewName): void
    {
        // asignamos colores
        foreach ($this->getServiceStatus() as $estado) {
            if (empty($estado->color)) {
                continue;
            }

            $this->views[$viewName]->getRow('status')->options[] = [
                'tag' => 'option',
                'children' => [],
                'color' => $estado->color,
                'fieldname' => 'idestado',
                'text' => $estado->id,
                'title' => $estado->nombre
            ];
        }
    }
}
