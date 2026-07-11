package pe.danielureta.fieldops.domain.repository

import kotlinx.coroutines.flow.Flow
import pe.danielureta.fieldops.domain.model.Evidence
import pe.danielureta.fieldops.domain.model.OrderStatus
import pe.danielureta.fieldops.domain.model.ServiceOrder
import pe.danielureta.fieldops.domain.model.SyncResult

interface OrderRepository {
    fun observeOrders(): Flow<List<ServiceOrder>>
    fun observeOrder(id: String): Flow<ServiceOrder?>
    fun observeEvidence(orderId: String): Flow<List<Evidence>>
    fun observePendingCount(): Flow<Int>
    fun hasAuthenticatedSession(): Boolean

    suspend fun authenticate(email: String, password: String)
    suspend fun bootstrap()
    suspend fun changeStatus(orderId: String, status: OrderStatus)
    suspend fun addSimulatedEvidence(orderId: String, note: String)
    suspend fun synchronize(): SyncResult
}
