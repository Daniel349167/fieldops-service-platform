package pe.danielureta.fieldops

import android.app.Application

class FieldOpsApplication : Application() {
    val container: AppContainer by lazy { AppContainer(this) }
}
