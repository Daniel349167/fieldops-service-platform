package pe.danielureta.fieldops.presentation

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.TestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import pe.danielureta.fieldops.domain.model.Evidence
import pe.danielureta.fieldops.domain.model.OrderStatus
import pe.danielureta.fieldops.domain.model.ServiceOrder
import pe.danielureta.fieldops.domain.model.SyncResult
import pe.danielureta.fieldops.domain.repository.OrderRepository

@OptIn(ExperimentalCoroutinesApi::class)
class FieldOpsViewModelTest {
    private val dispatcher: TestDispatcher = StandardTestDispatcher()

    @Before
    fun setUp() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `demo login only accepts documented account and then bootstraps`() = runTest(dispatcher) {
        val repository = FakeOrderRepository()
        val viewModel = FieldOpsViewModel(repository, demoMode = true)
        advanceUntilIdle()

        assertEquals(0, repository.bootstrapCalls)
        viewModel.login("otro@fieldops.pe", "incorrecta")
        assertFalse(viewModel.signedIn.value)
        assertEquals("Usa las credenciales demo indicadas", viewModel.message.value)

        viewModel.useDemoAccount()
        advanceUntilIdle()
        assertTrue(viewModel.signedIn.value)
        assertEquals(1, repository.authenticateCalls)
        assertEquals(1, repository.bootstrapCalls)
    }

    @Test
    fun `real login authenticates before loading work orders`() = runTest(dispatcher) {
        val repository = FakeOrderRepository()
        val viewModel = FieldOpsViewModel(repository, demoMode = false)

        assertEquals(0, repository.bootstrapCalls)
        viewModel.login("tecnico@fieldops.test", "FieldOps2026!")
        advanceUntilIdle()

        assertEquals(listOf("authenticate", "bootstrap"), repository.calls)
        assertTrue(viewModel.signedIn.value)
    }

    @Test
    fun `offline mode keeps mutations queued and never calls remote sync`() = runTest(dispatcher) {
        val repository = FakeOrderRepository(syncResult = SyncResult.Success(3))
        val viewModel = FieldOpsViewModel(repository, demoMode = true)
        advanceUntilIdle()

        viewModel.toggleConnectivity()
        viewModel.synchronize()
        advanceUntilIdle()

        assertFalse(viewModel.online.value)
        assertEquals(0, repository.syncCalls)
        assertEquals("Sin conexión: los cambios seguirán en cola", viewModel.message.value)
    }

    @Test
    fun `online synchronization reports how many queued changes were sent`() = runTest(dispatcher) {
        val repository = FakeOrderRepository(syncResult = SyncResult.Success(2))
        val viewModel = FieldOpsViewModel(repository, demoMode = true)
        advanceUntilIdle()

        viewModel.synchronize()
        advanceUntilIdle()

        assertEquals(1, repository.syncCalls)
        assertFalse(viewModel.syncing.value)
        assertEquals("2 cambio(s) sincronizado(s)", viewModel.message.value)
    }
}

private class FakeOrderRepository(
    private val syncResult: SyncResult = SyncResult.NoChanges,
    private val restoredSession: Boolean = false,
) : OrderRepository {
    private val orders = MutableStateFlow<List<ServiceOrder>>(emptyList())
    private val pending = MutableStateFlow(0)
    val calls = mutableListOf<String>()
    var syncCalls = 0
        private set
    var authenticateCalls = 0
        private set
    var bootstrapCalls = 0
        private set

    override fun observeOrders(): Flow<List<ServiceOrder>> = orders
    override fun observeOrder(id: String): Flow<ServiceOrder?> = MutableStateFlow(null)
    override fun observeEvidence(orderId: String): Flow<List<Evidence>> = MutableStateFlow(emptyList())
    override fun observePendingCount(): Flow<Int> = pending
    override fun hasAuthenticatedSession(): Boolean = restoredSession

    override suspend fun authenticate(email: String, password: String) {
        calls += "authenticate"
        authenticateCalls++
    }

    override suspend fun bootstrap() {
        calls += "bootstrap"
        bootstrapCalls++
    }

    override suspend fun changeStatus(orderId: String, status: OrderStatus) = Unit
    override suspend fun addSimulatedEvidence(orderId: String, note: String) = Unit

    override suspend fun synchronize(): SyncResult {
        syncCalls++
        return syncResult
    }
}
