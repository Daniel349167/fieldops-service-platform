package pe.danielureta.fieldops.presentation.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import pe.danielureta.fieldops.presentation.FieldOpsViewModel

private object Route {
    const val ORDERS = "orders"
    const val DETAIL = "detail/{orderId}"
    fun detail(orderId: String) = "detail/$orderId"
}

@Composable
fun FieldOpsApp(viewModel: FieldOpsViewModel) {
    val signedIn by viewModel.signedIn.collectAsStateWithLifecycle()
    val loggingIn by viewModel.loggingIn.collectAsStateWithLifecycle()
    val message by viewModel.message.collectAsStateWithLifecycle()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(message) {
        message?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeMessage()
        }
    }

    Box(Modifier.fillMaxSize()) {
        if (!signedIn) {
            LoginScreen(
                demoMode = viewModel.demoMode,
                loggingIn = loggingIn,
                onLogin = viewModel::login,
                onUseDemo = viewModel::useDemoAccount,
            )
        } else {
            val navController = rememberNavController()
            NavHost(navController = navController, startDestination = Route.ORDERS) {
                composable(Route.ORDERS) {
                    OrdersScreen(
                        viewModel = viewModel,
                        onOrderClick = { navController.navigate(Route.detail(it)) },
                    )
                }
                composable(
                    route = Route.DETAIL,
                    arguments = listOf(navArgument("orderId") { type = NavType.StringType }),
                ) { entry ->
                    OrderDetailScreen(
                        viewModel = viewModel,
                        orderId = requireNotNull(entry.arguments?.getString("orderId")),
                        onBack = navController::navigateUp,
                    )
                }
            }
        }

        SnackbarHost(
            hostState = snackbarHostState,
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(16.dp),
        )
    }
}
