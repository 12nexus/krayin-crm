<?php

namespace Nexus\Clients\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ClientDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $outstanding = DB::table('nexus_client_invoices')
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('nexus_client_invoices.client_id', 'nexus_clients.id')
            ->where('status', 'unpaid');

        $queryBuilder = DB::table('nexus_clients')
            ->leftJoin('users', 'users.id', '=', 'nexus_clients.user_id')
            ->addSelect(
                'nexus_clients.id',
                'nexus_clients.name',
                'nexus_clients.company',
                'nexus_clients.email',
                'nexus_clients.engagement_type',
                'nexus_clients.monthly_retainer',
                'nexus_clients.status',
                'nexus_clients.onboarded_on',
                'users.name as manager',
            )
            ->selectSub($outstanding, 'outstanding');

        $this->addFilter('id', 'nexus_clients.id');
        $this->addFilter('name', 'nexus_clients.name');
        $this->addFilter('company', 'nexus_clients.company');
        $this->addFilter('email', 'nexus_clients.email');
        $this->addFilter('engagement_type', 'nexus_clients.engagement_type');
        $this->addFilter('status', 'nexus_clients.status');
        $this->addFilter('onboarded_on', 'nexus_clients.onboarded_on');
        $this->addFilter('manager', 'users.name');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('clients::app.datagrid.name'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'company',
            'label'      => trans('clients::app.datagrid.company'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'engagement_type',
            'label'      => trans('clients::app.datagrid.engagement'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect(config('clients.engagement_types'))
                ->map(fn ($type) => ['label' => $type, 'value' => $type])
                ->all(),
        ]);

        $this->addColumn([
            'index'      => 'monthly_retainer',
            'label'      => trans('clients::app.datagrid.retainer'),
            'type'       => 'string',
            'sortable'   => true,
            'closure'    => fn ($row) => $row->monthly_retainer !== null ? core()->formatBasePrice($row->monthly_retainer) : '',
        ]);

        $this->addColumn([
            'index'      => 'outstanding',
            'label'      => trans('clients::app.datagrid.outstanding'),
            'type'       => 'string',
            'sortable'   => true,
            'closure'    => fn ($row) => core()->formatBasePrice($row->outstanding),
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('clients::app.datagrid.status'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => collect(config('clients.statuses'))
                ->map(fn ($label, $value) => ['label' => $label, 'value' => $value])
                ->values()
                ->all(),
            'closure'    => fn ($row) => config('clients.statuses')[$row->status] ?? $row->status,
        ]);

        $this->addColumn([
            'index'      => 'manager',
            'label'      => trans('clients::app.datagrid.manager'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'onboarded_on',
            'label'      => trans('clients::app.datagrid.onboarded-on'),
            'type'       => 'date',
            'sortable'   => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'closure'    => fn ($row) => $row->onboarded_on ? core()->formatDate($row->onboarded_on, 'd M Y') : '',
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => trans('clients::app.datagrid.view'),
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.clients.view', $row->id),
        ]);

        if (bouncer()->hasPermission('clients.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('clients::app.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.clients.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('clients.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('clients::app.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.clients.delete', $row->id),
            ]);
        }
    }
}
