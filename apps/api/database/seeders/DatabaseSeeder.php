<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\Evidence;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@fieldops.test'],
            ['name' => 'Daniel Admin', 'password' => Hash::make('FieldOps2026!'), 'role' => UserRole::Admin],
        );
        $technician = User::query()->updateOrCreate(
            ['email' => 'tecnico@fieldops.test'],
            ['name' => 'María Técnica', 'password' => Hash::make('FieldOps2026!'), 'role' => UserRole::Technician],
        );
        User::query()->updateOrCreate(
            ['email' => 'tecnico2@fieldops.test'],
            ['name' => 'Luis Técnico', 'password' => Hash::make('FieldOps2026!'), 'role' => UserRole::Technician],
        );

        $pending = $this->workOrder([
            'title' => 'Instalar punto de red en recepción',
            'description' => 'Validar cableado, instalar el punto y documentar las pruebas.',
            'customer_name' => 'Clínica San Gabriel',
            'customer_phone' => '+51 999 111 222',
            'customer_email' => 'operaciones@clinicasangabriel.test',
            'address_line' => 'Av. Arequipa 2450',
            'district' => 'Lince',
            'city' => 'Lima',
            'priority' => WorkOrderPriority::High,
            'status' => WorkOrderStatus::Pending,
            'scheduled_at' => now()->addDay()->setTime(9, 0),
            'version' => 1,
        ]);

        $assigned = $this->workOrder([
            'title' => 'Mantenimiento preventivo de terminal POS',
            'description' => 'Limpiar, actualizar y ejecutar pruebas de conectividad.',
            'customer_name' => 'Market Central',
            'customer_phone' => '+51 999 333 444',
            'address_line' => 'Jr. de la Unión 650',
            'district' => 'Cercado de Lima',
            'city' => 'Lima',
            'priority' => WorkOrderPriority::Normal,
            'status' => WorkOrderStatus::Assigned,
            'assigned_technician_id' => $technician->id,
            'scheduled_at' => now()->addHours(3),
            'version' => 1,
        ]);

        $completed = $this->workOrder([
            'title' => 'Reemplazar router de sucursal',
            'description' => 'Instalar equipo, restaurar configuración y validar acceso a internet.',
            'customer_name' => 'Distribuidora Pacífico',
            'customer_phone' => '+51 999 555 666',
            'address_line' => 'Av. La Marina 1800',
            'district' => 'San Miguel',
            'city' => 'Lima',
            'priority' => WorkOrderPriority::Urgent,
            'status' => WorkOrderStatus::Completed,
            'assigned_technician_id' => $technician->id,
            'scheduled_at' => now()->subDay(),
            'version' => 4,
        ]);

        foreach ([$pending, $assigned, $completed] as $workOrder) {
            $workOrder->events()->firstOrCreate(
                ['type' => 'created'],
                [
                    'actor_id' => $admin->id,
                    'to_status' => $workOrder->status->value,
                    'metadata' => ['version' => 1, 'seeded' => true],
                ],
            );
        }

        $evidence = Evidence::withTrashed()->updateOrCreate(
            ['work_order_id' => $completed->id, 'file_name' => 'router-instalado.jpg'],
            [
                'uploaded_by' => $technician->id,
                'mime_type' => 'image/jpeg',
                'size_bytes' => 245_760,
                'storage_path' => 'demo/router-instalado.jpg',
                'checksum' => hash('sha256', 'fieldops-demo-evidence'),
                'captured_at' => now()->subDay(),
                'metadata' => ['source' => 'mobile', 'seeded' => true],
                'version' => 1,
                'deleted_at' => null,
            ],
        );
        if (! $completed->events()->where('type', 'evidence_added')->where('metadata->evidence_id', $evidence->id)->exists()) {
            $completed->events()->create([
                'type' => 'evidence_added',
                'actor_id' => $technician->id,
                'metadata' => ['evidence_id' => $evidence->id, 'file_name' => $evidence->file_name, 'seeded' => true],
            ]);
        }
    }

    private function workOrder(array $attributes): WorkOrder
    {
        return WorkOrder::query()->updateOrCreate(
            ['title' => $attributes['title']],
            $attributes,
        );
    }
}
