package pe.danielureta.fieldops.presentation.ui

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowForward
import androidx.compose.material.icons.outlined.CloudDone
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.Engineering
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.Sync
import androidx.compose.material3.AssistChip
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import pe.danielureta.fieldops.domain.model.OrderPriority
import pe.danielureta.fieldops.domain.model.OrderStatus
import pe.danielureta.fieldops.domain.model.ServiceOrder
import pe.danielureta.fieldops.presentation.FieldOpsViewModel
import pe.danielureta.fieldops.presentation.ui.theme.FieldLime
import pe.danielureta.fieldops.presentation.ui.theme.FieldTealDark

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrdersScreen(
    viewModel: FieldOpsViewModel,
    onOrderClick: (String) -> Unit,
) {
    val orders by viewModel.orders.collectAsStateWithLifecycle()
    val pending by viewModel.pendingCount.collectAsStateWithLifecycle()
    val online by viewModel.online.collectAsStateWithLifecycle()
    val syncing by viewModel.syncing.collectAsStateWithLifecycle()
    var statusFilter by rememberSaveable { mutableStateOf<String?>(null) }
    val visibleOrders = orders.filter { statusFilter == null || it.status.name == statusFilter }

    Scaffold(
        topBar = {
            TopAppBar(
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = FieldTealDark,
                    titleContentColor = Color.White,
                    actionIconContentColor = Color.White,
                ),
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Outlined.Engineering, contentDescription = null)
                        Text("  FieldOps", fontWeight = FontWeight.Bold)
                    }
                },
                actions = {
                    IconButton(onClick = viewModel::toggleConnectivity) {
                        Icon(
                            imageVector = if (online) Icons.Outlined.CloudDone else Icons.Outlined.CloudOff,
                            contentDescription = if (online) "Simular modo offline" else "Restaurar conexión",
                            tint = if (online) FieldLime else Color(0xFFFFB29A),
                        )
                    }
                    IconButton(onClick = viewModel::synchronize, enabled = !syncing) {
                        if (syncing) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(22.dp),
                                strokeWidth = 2.dp,
                                color = Color.White,
                            )
                        } else {
                            Icon(Icons.Outlined.Sync, contentDescription = "Sincronizar")
                        }
                    }
                },
            )
        },
    ) { padding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .testTag("orders-list"),
            contentPadding = PaddingValues(bottom = 28.dp),
        ) {
            item {
                Column(
                    modifier = Modifier.padding(horizontal = 18.dp, vertical = 18.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.Top,
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "Buenos días, Daniel",
                                style = MaterialTheme.typography.headlineSmall,
                                fontWeight = FontWeight.Bold,
                            )
                            Text(
                                text = "Tienes ${orders.count { it.status != OrderStatus.COMPLETED }} servicios por atender",
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        if (viewModel.demoMode) DemoTag()
                    }

                    AnimatedVisibility(visible = !online || pending > 0) {
                        ConnectivityCard(online = online, pending = pending)
                    }

                    Text(
                        text = "ÓRDENES DE SERVICIO",
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.primary,
                        fontWeight = FontWeight.Bold,
                    )
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                    ) {
                        FilterChip(
                            selected = statusFilter == null,
                            onClick = { statusFilter = null },
                            label = { Text("Todas") },
                        )
                        OrderStatus.entries.forEach { status ->
                            FilterChip(
                                selected = statusFilter == status.name,
                                onClick = { statusFilter = status.name },
                                label = { Text(status.label) },
                            )
                        }
                    }
                }
            }

            if (orders.isEmpty()) {
                item {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(48.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        CircularProgressIndicator()
                    }
                }
            } else if (visibleOrders.isEmpty()) {
                item {
                    Text(
                        text = "No hay órdenes con este estado.",
                        modifier = Modifier.padding(horizontal = 18.dp, vertical = 28.dp),
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            } else {
                items(visibleOrders, key = ServiceOrder::id) { order ->
                    OrderCard(
                        order = order,
                        onClick = { onOrderClick(order.id) },
                        modifier = Modifier.padding(horizontal = 18.dp, vertical = 7.dp),
                    )
                }
            }
        }
    }
}

@Composable
private fun DemoTag() {
    Surface(
        color = FieldLime,
        contentColor = FieldTealDark,
        shape = RoundedCornerShape(8.dp),
    ) {
        Text(
            text = "DEMO",
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Black,
        )
    }
}

@Composable
private fun ConnectivityCard(online: Boolean, pending: Int) {
    val background = if (online) {
        MaterialTheme.colorScheme.primaryContainer
    } else {
        MaterialTheme.colorScheme.tertiaryContainer
    }
    Card(
        colors = CardDefaults.cardColors(containerColor = background),
        shape = RoundedCornerShape(16.dp),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(
                imageVector = if (online) Icons.Outlined.Sync else Icons.Outlined.CloudOff,
                contentDescription = null,
            )
            Column(modifier = Modifier.padding(start = 12.dp)) {
                Text(
                    text = if (online) "$pending cambio(s) por sincronizar" else "Trabajando sin conexión",
                    fontWeight = FontWeight.Bold,
                )
                Text(
                    text = if (online) "Pulsa sincronizar para enviar la cola." else "Tus avances se guardan de forma segura.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }
        }
    }
}

@Composable
private fun OrderCard(
    order: ServiceOrder,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(11.dp),
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = order.code,
                    color = MaterialTheme.colorScheme.primary,
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold,
                )
                StatusPill(order.status)
            }
            Text(
                text = order.title,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
            )
            Text(
                text = order.customer,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            InfoRow(Icons.Outlined.LocationOn, order.address)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                InfoRow(Icons.Outlined.Schedule, order.schedule)
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        Modifier
                            .size(7.dp)
                            .background(priorityColor(order.priority), CircleShape),
                    )
                    Text(
                        text = "  Prioridad ${order.priority.label.lowercase()}",
                        style = MaterialTheme.typography.labelMedium,
                    )
                    Icon(
                        Icons.AutoMirrored.Outlined.ArrowForward,
                        contentDescription = null,
                        modifier = Modifier
                            .padding(start = 8.dp)
                            .size(18.dp),
                    )
                }
            }
        }
    }
}

@Composable
internal fun StatusPill(status: OrderStatus) {
    AssistChip(
        onClick = {},
        label = { Text(status.label) },
        leadingIcon = {
            Box(
                Modifier
                    .size(7.dp)
                    .background(statusColor(status), CircleShape),
            )
        },
    )
}

@Composable
private fun InfoRow(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.size(17.dp),
        )
        Text(
            text = "  $text",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}

@Composable
internal fun statusColor(status: OrderStatus): Color = when (status) {
    OrderStatus.PENDING -> Color(0xFF7A6A50)
    OrderStatus.ASSIGNED -> MaterialTheme.colorScheme.outline
    OrderStatus.EN_ROUTE -> Color(0xFF2D77C7)
    OrderStatus.IN_PROGRESS -> Color(0xFFE48725)
    OrderStatus.COMPLETED -> Color(0xFF2B8A56)
    OrderStatus.CANCELLED -> Color(0xFFB34B4B)
}

@Composable
private fun priorityColor(priority: OrderPriority): Color = when (priority) {
    OrderPriority.LOW -> Color(0xFF4B8B70)
    OrderPriority.NORMAL -> Color(0xFFE0A12B)
    OrderPriority.HIGH -> Color(0xFFD7584D)
    OrderPriority.URGENT -> Color(0xFFB21F35)
}
