package pe.danielureta.fieldops

import android.content.Context
import androidx.room.Room
import pe.danielureta.fieldops.data.local.FieldOpsDatabase
import pe.danielureta.fieldops.data.remote.NetworkFactory
import pe.danielureta.fieldops.data.remote.SharedPreferencesAuthSessionStore
import pe.danielureta.fieldops.data.repository.OfflineFirstOrderRepository
import pe.danielureta.fieldops.domain.repository.OrderRepository

class AppContainer(context: Context) {
    private val database = Room.databaseBuilder(
        context,
        FieldOpsDatabase::class.java,
        "fieldops.db",
    ).fallbackToDestructiveMigration().build()

    private val sessionStore = SharedPreferencesAuthSessionStore(context)
    private val api = NetworkFactory.create(BuildConfig.FIELDOPS_API_BASE_URL, sessionStore)

    val orderRepository: OrderRepository = OfflineFirstOrderRepository(
        database = database,
        api = api,
        sessionStore = sessionStore,
        demoMode = BuildConfig.FIELDOPS_DEMO_MODE,
    )
}
