package pe.danielureta.fieldops.data.repository

import androidx.room.withTransaction
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale
import java.util.UUID
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import pe.danielureta.fieldops.data.local.EvidenceEntity
import pe.danielureta.fieldops.data.local.FieldOpsDatabase
import pe.danielureta.fieldops.data.local.MutationType
import pe.danielureta.fieldops.data.local.PendingMutationEntity
import pe.danielureta.fieldops.data.local.ServiceOrderEntity
import pe.danielureta.fieldops.data.remote.AuthSessionStore
import pe.danielureta.fieldops.data.remote.FieldOpsApi
import pe.danielureta.fieldops.data.remote.LoginRequest
import pe.danielureta.fieldops.data.remote.TransitionWorkOrderRequest
import pe.danielureta.fieldops.data.remote.WorkOrderDto
import pe.danielureta.fieldops.domain.model.Evidence
import pe.danielureta.fieldops.domain.model.OrderPriority
import pe.danielureta.fieldops.domain.model.OrderStatus
import pe.danielureta.fieldops.domain.model.OrderStatusPolicy
import pe.danielureta.fieldops.domain.model.ServiceOrder
import pe.danielureta.fieldops.domain.model.SyncResult
import pe.danielureta.fieldops.domain.repository.OrderRepository

class OfflineFirstOrderRepository(
    private val database: FieldOpsDatabase,
    private val api: FieldOpsApi,
    private val sessionStore: AuthSessionStore,
    private val demoMode: Boolean,
) : OrderRepository {
    private val orders = database.serviceOrderDao()
    private val evidence = database.evidenceDao()
    private val pending = database.pendingMutationDao()

    override fun observeOrders(): Flow<List<ServiceOrder>> =
        orders.observeAll().map { rows -> rows.map(ServiceOrderEntity::toDomain) }

    override fun observeOrder(id: String): Flow<ServiceOrder?> =
        orders.observeById(id).map { it?.toDomain() }

    override fun observeEvidence(orderId: String): Flow<List<Evidence>> =
        evidence.observeByOrder(orderId).map { rows -> rows.map(EvidenceEntity::toDomain) }

    override fun observePendingCount(): Flow<Int> = pending.observeCount()

    override fun hasAuthenticatedSession(): Boolean = !demoMode && sessionStore.hasToken()

    override suspend fun authenticate(email: String, password: String) {
        if (demoMode) return
        val response = api.login(LoginRequest(email = email.trim(), password = password))
        sessionStore.saveToken(response.data.token)
    }

    override suspend fun bootstrap() {
        if (demoMode) {
            if (orders.count() == 0) orders.upsertAll(DemoOrders.values)
            return
        }

        check(sessionStore.hasToken()) { "Inicia sesión antes de cargar las órdenes" }
        val remoteOrders = api.getWorkOrders().data.map(WorkOrderDto::toEntity)
        orders.upsertAll(remoteOrders)
    }

    override suspend fun changeStatus(orderId: String, status: OrderStatus) {
        val current = requireNotNull(orders.getById(orderId)) { "Orden no encontrada" }
        val currentStatus = enumValueOf<OrderStatus>(current.status)
        require(OrderStatusPolicy.canTransition(currentStatus, status)) {
            "Transición no permitida: ${currentStatus.label} → ${status.label}"
        }

        val now = System.currentTimeMillis()
        database.withTransaction {
            orders.updateStatus(orderId, status.name, current.version + 1, now)
            pending.insert(
                PendingMutationEntity(
                    orderId = orderId,
                    type = MutationType.STATUS,
                    targetStatus = status.toApiValue(),
                    expectedVersion = current.version,
                    idempotencyKey = UUID.randomUUID().toString(),
                    createdAt = now,
                ),
            )
        }
    }

    override suspend fun addSimulatedEvidence(orderId: String, note: String) {
        check(demoMode) { "La evidencia simulada solo está disponible en la demo" }
        val order = requireNotNull(orders.getById(orderId)) { "Orden no encontrada" }
        val now = System.currentTimeMillis()
        val evidenceId = UUID.randomUUID().toString()
        val item = EvidenceEntity(
            id = evidenceId,
            orderId = orderId,
            fileName = "evidencia-demo-$now.jpg",
            note = note.ifBlank { "Evidencia visual del servicio" },
            capturedAt = now,
            simulated = true,
        )

        database.withTransaction {
            evidence.insert(item)
            pending.insert(
                PendingMutationEntity(
                    orderId = orderId,
                    type = MutationType.EVIDENCE,
                    evidenceId = evidenceId,
                    expectedVersion = order.version,
                    idempotencyKey = UUID.randomUUID().toString(),
                    createdAt = now,
                ),
            )
        }
    }

    override suspend fun synchronize(): SyncResult {
        val mutations = pending.getAll()
        if (mutations.isEmpty()) return SyncResult.NoChanges

        if (demoMode) {
            delay(700)
            pending.deleteAll()
            return SyncResult.Success(mutations.size)
        }

        var synchronized = 0
        for (mutation in mutations) {
            try {
                when (mutation.type) {
                    MutationType.STATUS -> {
                        val response = api.transitionWorkOrder(
                            id = mutation.orderId,
                            idempotencyKey = mutation.idempotencyKey,
                            body = TransitionWorkOrderRequest(
                                toStatus = requireNotNull(mutation.targetStatus),
                                version = mutation.expectedVersion,
                            ),
                        ).data
                        val responseUpdatedAt = response.updatedAt.toEpochMillis()
                        val hasLaterStatus = mutations.any {
                            it.id > mutation.id &&
                                it.orderId == mutation.orderId &&
                                it.type == MutationType.STATUS
                        }
                        database.withTransaction {
                            if (!hasLaterStatus) {
                                orders.updateStatus(
                                    mutation.orderId,
                                    response.status.toOrderStatus().name,
                                    response.version,
                                    responseUpdatedAt,
                                )
                            }
                            pending.delete(mutation.id)
                        }
                    }

                    MutationType.EVIDENCE -> {
                        // A simulated Room attachment is never represented as a real backend file.
                        return SyncResult.Failure(
                            pending = mutations.size - synchronized,
                            reason = "La evidencia demo no se envía al backend real",
                        )
                    }

                    else -> error("Tipo de mutación no soportado: ${mutation.type}")
                }
                synchronized++
            } catch (exception: Exception) {
                return SyncResult.Failure(
                    pending = mutations.size - synchronized,
                    reason = exception.message ?: "No se pudo contactar al servidor",
                )
            }
        }
        return SyncResult.Success(synchronized)
    }
}

private fun ServiceOrderEntity.toDomain() = ServiceOrder(
    id = id,
    code = code,
    title = title,
    customer = customer,
    address = address,
    schedule = schedule,
    priority = enumValueOf(priority),
    status = enumValueOf(status),
    description = description,
    contactName = contactName,
    contactPhone = contactPhone,
    version = version,
    updatedAt = updatedAt,
)

private fun EvidenceEntity.toDomain() = Evidence(
    id = id,
    orderId = orderId,
    fileName = fileName,
    note = note,
    capturedAt = capturedAt,
    simulated = simulated,
)

private fun WorkOrderDto.toEntity() = ServiceOrderEntity(
    id = id,
    code = "OT-${id.takeLast(6).uppercase()}",
    title = title,
    customer = customer.name,
    address = listOfNotNull(address.line, address.district, address.city)
        .filter(String::isNotBlank)
        .distinct()
        .joinToString(", "),
    schedule = scheduledAt.toScheduleLabel(),
    priority = priority.toOrderPriority().name,
    status = status.toOrderStatus().name,
    description = description.orEmpty(),
    contactName = customer.name,
    contactPhone = customer.phone.orEmpty(),
    version = version,
    updatedAt = updatedAt.toEpochMillis(),
)

private fun String.toOrderPriority(): OrderPriority = when (lowercase()) {
    "low" -> OrderPriority.LOW
    "normal" -> OrderPriority.NORMAL
    "high" -> OrderPriority.HIGH
    "urgent" -> OrderPriority.URGENT
    else -> error("Prioridad desconocida: $this")
}

private fun String.toOrderStatus(): OrderStatus = when (lowercase()) {
    "pending" -> OrderStatus.PENDING
    "assigned" -> OrderStatus.ASSIGNED
    "en_route" -> OrderStatus.EN_ROUTE
    "in_progress" -> OrderStatus.IN_PROGRESS
    "completed" -> OrderStatus.COMPLETED
    "cancelled" -> OrderStatus.CANCELLED
    else -> error("Estado desconocido: $this")
}

private fun OrderStatus.toApiValue(): String = name.lowercase()

private fun String?.toEpochMillis(): Long = this?.let { value ->
    runCatching { OffsetDateTime.parse(value).toInstant().toEpochMilli() }.getOrNull()
} ?: System.currentTimeMillis()

private fun String?.toScheduleLabel(): String = this?.let { value ->
    runCatching {
        OffsetDateTime.parse(value)
            .atZoneSameInstant(ZoneId.systemDefault())
            .format(DateTimeFormatter.ofPattern("dd MMM · HH:mm", Locale("es", "PE")))
    }.getOrDefault(value)
} ?: "Por coordinar"

private object DemoOrders {
    private val now = System.currentTimeMillis()

    val values = listOf(
        ServiceOrderEntity(
            id = "wo-1048",
            code = "OT-1048",
            title = "Mantenimiento preventivo",
            customer = "Clínica San Gabriel",
            address = "Av. Javier Prado 2180, San Isidro",
            schedule = "Hoy · 09:30",
            priority = OrderPriority.HIGH.name,
            status = OrderStatus.EN_ROUTE.name,
            description = "Revisión preventiva del tablero eléctrico y validación de carga del área de laboratorio.",
            contactName = "María Calderón",
            contactPhone = "+51 987 220 145",
            version = 2,
            updatedAt = now - 18 * 60_000,
        ),
        ServiceOrderEntity(
            id = "wo-1051",
            code = "OT-1051",
            title = "Instalación de terminal POS",
            customer = "Mercado Central Surco",
            address = "Jr. Bolognesi 421, Santiago de Surco",
            schedule = "Hoy · 11:45",
            priority = OrderPriority.NORMAL.name,
            status = OrderStatus.ASSIGNED.name,
            description = "Instalar terminal, configurar red segura y capacitar al responsable de caja.",
            contactName = "Luis Salazar",
            contactPhone = "+51 944 702 118",
            version = 1,
            updatedAt = now - 42 * 60_000,
        ),
        ServiceOrderEntity(
            id = "wo-1042",
            code = "OT-1042",
            title = "Diagnóstico de conectividad",
            customer = "Distribuidora Andina",
            address = "Av. Argentina 1580, Callao",
            schedule = "Hoy · 14:00",
            priority = OrderPriority.URGENT.name,
            status = OrderStatus.IN_PROGRESS.name,
            description = "Intermitencia en almacén principal. Revisar puntos de acceso, cableado y latencia.",
            contactName = "Rosa Paredes",
            contactPhone = "+51 912 330 486",
            version = 3,
            updatedAt = now - 8 * 60_000,
        ),
        ServiceOrderEntity(
            id = "wo-1037",
            code = "OT-1037",
            title = "Cambio de sensor de acceso",
            customer = "Edificio Los Cedros",
            address = "Calle Las Begonias 310, San Isidro",
            schedule = "Mañana · 08:30",
            priority = OrderPriority.LOW.name,
            status = OrderStatus.ASSIGNED.name,
            description = "Reemplazo del sensor de la puerta principal y prueba del registro de accesos.",
            contactName = "Diego Meza",
            contactPhone = "+51 966 581 004",
            version = 1,
            updatedAt = now - 2 * 60 * 60_000,
        ),
    )
}
