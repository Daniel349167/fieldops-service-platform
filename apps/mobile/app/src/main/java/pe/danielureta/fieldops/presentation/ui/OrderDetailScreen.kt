package pe.danielureta.fieldops.presentation.ui

import androidx.compose.foundation.background
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.AddAPhoto
import androidx.compose.material.icons.outlined.CheckCircle
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.Description
import androidx.compose.material.icons.outlined.LocationOn
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.Phone
import androidx.compose.material.icons.outlined.Schedule
import androidx.compose.material.icons.outlined.Sync
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import pe.danielureta.fieldops.domain.model.Evidence
import pe.danielureta.fieldops.domain.model.OrderStatus
import pe.danielureta.fieldops.domain.model.OrderStatusPolicy
import pe.danielureta.fieldops.domain.model.ServiceOrder
import pe.danielureta.fieldops.presentation.FieldOpsViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrderDetailScreen(
    viewModel: FieldOpsViewModel,
    orderId: String,
    onBack: () -> Unit,
) {
    val orderFlow = remember(orderId) { viewModel.order(orderId) }
    val evidenceFlow = remember(orderId) { viewModel.evidence(orderId) }
    val order by orderFlow.collectAsStateWithLifecycle(initialValue = null)
    val evidence by evidenceFlow.collectAsStateWithLifecycle(initialValue = emptyList())
    val online by viewModel.online.collectAsStateWithLifecycle()
    val pending by viewModel.pendingCount.collectAsStateWithLifecycle()
    var showEvidenceDialog by rememberSaveable { mutableStateOf(false) }

    if (showEvidenceDialog && viewModel.demoMode) {
        EvidenceDialog(
            onDismiss = { showEvidenceDialog = false },
            onConfirm = { note ->
                showEvidenceDialog = false
                viewModel.addSimulatedEvidence(orderId, note)
            },
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Detalle de orden", fontWeight = FontWeight.Bold)
                        order?.let {
                            Text(
                                text = it.code,
                                style = MaterialTheme.typography.labelMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Outlined.ArrowBack, contentDescription = "Volver")
                    }
                },
                actions = {
                    IconButton(onClick = viewModel::synchronize) {
                        Icon(Icons.Outlined.Sync, contentDescription = "Sincronizar")
                    }
                },
            )
        },
    ) { padding ->
        val current = order
        if (current == null) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
                contentAlignment = Alignment.Center,
            ) {
                Text("Cargando orden…")
            }
        } else {
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
                contentPadding = PaddingValues(horizontal = 18.dp, vertical = 14.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                if (!online || pending > 0) {
                    item {
                        Surface(
                            color = if (online) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.tertiaryContainer,
                            shape = RoundedCornerShape(14.dp),
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(13.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Icon(
                                    if (online) Icons.Outlined.Sync else Icons.Outlined.CloudOff,
                                    contentDescription = null,
                                )
                                Text(
                                    text = if (online) "  $pending cambio(s) en cola" else "  Modo sin conexión · cambios protegidos",
                                    fontWeight = FontWeight.SemiBold,
                                )
                            }
                        }
                    }
                }

                item { OrderSummaryCard(current) }
                item {
                    ProgressCard(
                        order = current,
                        onStatusChange = { viewModel.changeStatus(current.id, it) },
                    )
                }
                item { ContactCard(current) }
                if (viewModel.demoMode) {
                    item {
                        EvidenceHeader(
                            count = evidence.size,
                            onCapture = { showEvidenceDialog = true },
                        )
                    }
                    if (evidence.isEmpty()) {
                        item { EmptyEvidenceCard() }
                    } else {
                        items(evidence, key = Evidence::id) { item -> EvidenceCard(item) }
                    }
                }
                item { Spacer(Modifier.height(18.dp)) }
            }
        }
    }
}

@Composable
private fun OrderSummaryCard(order: ServiceOrder) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        shape = RoundedCornerShape(20.dp),
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    text = order.customer,
                    color = MaterialTheme.colorScheme.primary,
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold,
                )
                StatusPill(order.status)
            }
            Text(
                text = order.title,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
            )
            Text(
                text = order.description,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            HorizontalDivider()
            DetailLine(Icons.Outlined.Schedule, "Horario", order.schedule)
            DetailLine(Icons.Outlined.LocationOn, "Dirección", order.address)
        }
    }
}

@Composable
private fun ProgressCard(
    order: ServiceOrder,
    onStatusChange: (OrderStatus) -> Unit,
) {
    val next = OrderStatusPolicy.allowedNext(order.status).firstOrNull()
    val workflow = listOf(
        OrderStatus.PENDING,
        OrderStatus.ASSIGNED,
        OrderStatus.EN_ROUTE,
        OrderStatus.IN_PROGRESS,
        OrderStatus.COMPLETED,
    )
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        shape = RoundedCornerShape(20.dp),
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Text("Avance del servicio", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            workflow.forEachIndexed { index, status ->
                val currentIndex = workflow.indexOf(order.status)
                val reached = currentIndex >= 0 && index <= currentIndex
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(24.dp)
                            .background(
                                if (reached) statusColor(status) else MaterialTheme.colorScheme.surfaceVariant,
                                CircleShape,
                            ),
                        contentAlignment = Alignment.Center,
                    ) {
                        if (reached) {
                            Icon(
                                Icons.Outlined.CheckCircle,
                                contentDescription = null,
                                tint = Color.White,
                                modifier = Modifier.size(17.dp),
                            )
                        }
                    }
                    Column(modifier = Modifier.padding(start = 12.dp)) {
                        Text(status.label, fontWeight = if (status == order.status) FontWeight.Bold else FontWeight.Normal)
                        if (status == order.status) {
                            Text("Estado actual", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary)
                        }
                    }
                }
                if (index < workflow.lastIndex) {
                    Box(
                        Modifier
                            .padding(start = 11.dp)
                            .size(width = 2.dp, height = 12.dp)
                            .background(MaterialTheme.colorScheme.outlineVariant),
                    )
                }
            }

            if (next != null) {
                Button(
                    onClick = { onStatusChange(next) },
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    Text("Continuar a ${next.label}")
                }
            } else {
                Surface(
                    color = MaterialTheme.colorScheme.primaryContainer,
                    shape = RoundedCornerShape(12.dp),
                ) {
                    Text(
                        text = if (order.status == OrderStatus.CANCELLED) "Servicio cancelado" else "Servicio completado",
                        modifier = Modifier.padding(13.dp),
                        color = MaterialTheme.colorScheme.onPrimaryContainer,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }
        }
    }
}

@Composable
private fun ContactCard(order: ServiceOrder) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        shape = RoundedCornerShape(20.dp),
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Contacto en sitio", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            DetailLine(Icons.Outlined.Person, "Responsable", order.contactName)
            DetailLine(Icons.Outlined.Phone, "Teléfono", order.contactPhone)
        }
    }
}

@Composable
private fun EvidenceHeader(count: Int, onCapture: () -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column {
            Text("Evidencias", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text("$count adjunto(s)", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        FilledTonalButton(onClick = onCapture) {
            Icon(Icons.Outlined.AddAPhoto, contentDescription = null)
            Text("  Simular captura")
        }
    }
}

@Composable
private fun EmptyEvidenceCard() {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.45f)),
        shape = RoundedCornerShape(18.dp),
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(
                Icons.Outlined.AddAPhoto,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(34.dp),
            )
            Text("Aún no hay evidencias", fontWeight = FontWeight.SemiBold)
            Text(
                "La demo genera un adjunto local identificado como simulado.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

@Composable
private fun EvidenceCard(evidence: Evidence) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        shape = RoundedCornerShape(16.dp),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                modifier = Modifier
                    .size(58.dp)
                    .background(MaterialTheme.colorScheme.primaryContainer, RoundedCornerShape(12.dp)),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Outlined.Description, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            }
            Column(
                modifier = Modifier
                    .weight(1f)
                    .padding(start = 12.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Evidencia simulada", fontWeight = FontWeight.Bold)
                    Surface(
                        modifier = Modifier.padding(start = 7.dp),
                        color = MaterialTheme.colorScheme.tertiaryContainer,
                        shape = RoundedCornerShape(6.dp),
                    ) {
                        Text("DEMO", modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp), style = MaterialTheme.typography.labelSmall)
                    }
                }
                Text(evidence.note, maxLines = 2, overflow = TextOverflow.Ellipsis, style = MaterialTheme.typography.bodySmall)
                Text(formatTimestamp(evidence.capturedAt), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun DetailLine(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    value: String,
) {
    Row(verticalAlignment = Alignment.Top) {
        Icon(
            icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.primary,
            modifier = Modifier
                .padding(top = 2.dp)
                .size(20.dp),
        )
        Column(modifier = Modifier.padding(start = 12.dp)) {
            Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(value, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun EvidenceDialog(
    onDismiss: () -> Unit,
    onConfirm: (String) -> Unit,
) {
    var note by rememberSaveable { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        icon = { Icon(Icons.Outlined.AddAPhoto, contentDescription = null) },
        title = { Text("Simular evidencia") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    "Esta versión de portafolio no abre la cámara: crea un adjunto demostrativo en Room y lo agrega a la cola offline.",
                )
                OutlinedTextField(
                    value = note,
                    onValueChange = { note = it },
                    label = { Text("Nota de trabajo") },
                    minLines = 2,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
        confirmButton = {
            Button(onClick = { onConfirm(note) }) { Text("Crear adjunto demo") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancelar") }
        },
    )
}

private fun formatTimestamp(timestamp: Long): String =
    SimpleDateFormat("dd MMM · HH:mm", Locale("es", "PE")).format(Date(timestamp))
