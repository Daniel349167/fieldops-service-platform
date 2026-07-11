package pe.danielureta.fieldops

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import pe.danielureta.fieldops.presentation.FieldOpsViewModel
import pe.danielureta.fieldops.presentation.FieldOpsViewModelFactory
import pe.danielureta.fieldops.presentation.ui.FieldOpsApp
import pe.danielureta.fieldops.presentation.ui.theme.FieldOpsTheme

class MainActivity : ComponentActivity() {
    private val viewModel: FieldOpsViewModel by viewModels {
        val application = application as FieldOpsApplication
        FieldOpsViewModelFactory(
            repository = application.container.orderRepository,
            demoMode = BuildConfig.FIELDOPS_DEMO_MODE,
        )
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            FieldOpsTheme {
                FieldOpsApp(viewModel)
            }
        }
    }
}
