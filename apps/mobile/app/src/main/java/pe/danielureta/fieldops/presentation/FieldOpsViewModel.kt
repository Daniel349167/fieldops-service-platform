package pe.danielureta.fieldops.presentation

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import pe.danielureta.fieldops.domain.model.Evidence
import pe.danielureta.fieldops.domain.model.OrderStatus
import pe.danielureta.fieldops.domain.model.ServiceOrder
import pe.danielureta.fieldops.domain.model.SyncResult
import pe.danielureta.fieldops.domain.repository.OrderRepository

class FieldOpsViewModel(
    private val repository: OrderRepository,
    val demoMode: Boolean,
) : ViewModel() {
    val orders: StateFlow<List<ServiceOrder>> = repository.observeOrders().stateIn(
        scope = viewModelScope,
        started = SharingStarted.WhileSubscribed(5_000),
        initialValue = emptyList(),
    )
    val pendingCount: StateFlow<Int> = repository.observePendingCount().stateIn(
        scope = viewModelScope,
        started = SharingStarted.WhileSubscribed(5_000),
        initialValue = 0,
    )

    private val _signedIn = MutableStateFlow(false)
    val signedIn: StateFlow<Boolean> = _signedIn.asStateFlow()

    private val _online = MutableStateFlow(true)
    val online: StateFlow<Boolean> = _online.asStateFlow()

    private val _syncing = MutableStateFlow(false)
    val syncing: StateFlow<Boolean> = _syncing.asStateFlow()

    private val _loggingIn = MutableStateFlow(false)
    val loggingIn: StateFlow<Boolean> = _loggingIn.asStateFlow()

    private val _message = MutableStateFlow<String?>(null)
    val message: StateFlow<String?> = _message.asStateFlow()

    init {
        if (repository.hasAuthenticatedSession()) {
            viewModelScope.launch { completeSignIn(email = null, password = null) }
        }
    }

    fun login(email: String, password: String) {
        if (_loggingIn.value) return
        if (demoMode && (!email.trim().equals(DEMO_EMAIL, ignoreCase = true) || password != DEMO_PASSWORD)) {
            _message.value = "Usa las credenciales demo indicadas"
            return
        }
        if (!demoMode && (email.isBlank() || password.isBlank())) {
            _message.value = "Ingresa tu correo y contraseña"
            return
        }

        viewModelScope.launch { completeSignIn(email.trim(), password) }
    }

    fun useDemoAccount() = login(DEMO_EMAIL, DEMO_PASSWORD)

    fun order(id: String): Flow<ServiceOrder?> = repository.observeOrder(id)
    fun evidence(id: String): Flow<List<Evidence>> = repository.observeEvidence(id)

    fun changeStatus(orderId: String, status: OrderStatus) {
        viewModelScope.launch {
            runCatching { repository.changeStatus(orderId, status) }
                .onSuccess { _message.value = "Estado guardado en la cola offline" }
                .onFailure { _message.value = it.message ?: "No se pudo actualizar la orden" }
        }
    }

    fun addSimulatedEvidence(orderId: String, note: String) {
        viewModelScope.launch {
            runCatching { repository.addSimulatedEvidence(orderId, note) }
                .onSuccess { _message.value = "Evidencia simulada guardada localmente" }
                .onFailure { _message.value = it.message ?: "No se pudo guardar la evidencia" }
        }
    }

    fun toggleConnectivity() {
        _online.value = !_online.value
        _message.value = if (_online.value) "Conexión restaurada" else "Modo sin conexión activado"
    }

    fun synchronize() {
        if (!_online.value) {
            _message.value = "Sin conexión: los cambios seguirán en cola"
            return
        }
        if (_syncing.value) return

        viewModelScope.launch {
            _syncing.value = true
            _message.value = when (val result = repository.synchronize()) {
                SyncResult.NoChanges -> "No hay cambios pendientes"
                is SyncResult.Success -> "${result.synchronized} cambio(s) sincronizado(s)"
                is SyncResult.Failure -> "Quedan ${result.pending} cambios: ${result.reason}"
            }
            _syncing.value = false
        }
    }

    fun consumeMessage() {
        _message.value = null
    }

    private suspend fun completeSignIn(email: String?, password: String?) {
        _loggingIn.value = true
        runCatching {
            if (email != null && password != null) repository.authenticate(email, password)
            repository.bootstrap()
        }.onSuccess {
            _signedIn.value = true
        }.onFailure {
            _signedIn.value = false
            _message.value = it.message ?: "No se pudo iniciar sesión"
        }
        _loggingIn.value = false
    }

    companion object {
        const val DEMO_EMAIL = "demo@fieldops.pe"
        const val DEMO_PASSWORD = "demo123"
    }
}

class FieldOpsViewModelFactory(
    private val repository: OrderRepository,
    private val demoMode: Boolean,
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        require(modelClass.isAssignableFrom(FieldOpsViewModel::class.java))
        return FieldOpsViewModel(repository, demoMode) as T
    }
}
