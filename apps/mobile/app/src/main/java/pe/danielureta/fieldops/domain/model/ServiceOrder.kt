package pe.danielureta.fieldops.domain.model

data class ServiceOrder(
    val id: String,
    val code: String,
    val title: String,
    val customer: String,
    val address: String,
    val schedule: String,
    val priority: OrderPriority,
    val status: OrderStatus,
    val description: String,
    val contactName: String,
    val contactPhone: String,
    val version: Long,
    val updatedAt: Long,
)

enum class OrderPriority(val label: String) {
    LOW("Baja"),
    NORMAL("Normal"),
    HIGH("Alta"),
    URGENT("Urgente"),
}

enum class OrderStatus(val label: String) {
    PENDING("Pendiente"),
    ASSIGNED("Asignada"),
    EN_ROUTE("En camino"),
    IN_PROGRESS("En atención"),
    COMPLETED("Completada"),
    CANCELLED("Cancelada"),
}

object OrderStatusPolicy {
    fun allowedNext(current: OrderStatus): List<OrderStatus> = when (current) {
        OrderStatus.PENDING -> listOf(OrderStatus.ASSIGNED)
        OrderStatus.ASSIGNED -> listOf(OrderStatus.EN_ROUTE)
        OrderStatus.EN_ROUTE -> listOf(OrderStatus.IN_PROGRESS)
        OrderStatus.IN_PROGRESS -> listOf(OrderStatus.COMPLETED)
        OrderStatus.COMPLETED, OrderStatus.CANCELLED -> emptyList()
    }

    fun canTransition(from: OrderStatus, to: OrderStatus): Boolean = to in allowedNext(from)
}

data class Evidence(
    val id: String,
    val orderId: String,
    val fileName: String,
    val note: String,
    val capturedAt: Long,
    val simulated: Boolean,
)

sealed interface SyncResult {
    data object NoChanges : SyncResult
    data class Success(val synchronized: Int) : SyncResult
    data class Failure(val pending: Int, val reason: String) : SyncResult
}
