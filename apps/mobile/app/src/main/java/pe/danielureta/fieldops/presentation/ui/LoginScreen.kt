package pe.danielureta.fieldops.presentation.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Bolt
import androidx.compose.material.icons.outlined.CloudDone
import androidx.compose.material.icons.outlined.Engineering
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import pe.danielureta.fieldops.presentation.FieldOpsViewModel
import pe.danielureta.fieldops.presentation.ui.theme.FieldLime
import pe.danielureta.fieldops.presentation.ui.theme.FieldTealDark

@Composable
fun LoginScreen(
    demoMode: Boolean,
    loggingIn: Boolean,
    onLogin: (String, String) -> Unit,
    onUseDemo: () -> Unit,
) {
    var email by rememberSaveable { mutableStateOf(if (demoMode) FieldOpsViewModel.DEMO_EMAIL else "") }
    var password by rememberSaveable { mutableStateOf(if (demoMode) FieldOpsViewModel.DEMO_PASSWORD else "") }

    Surface(color = FieldTealDark) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 24.dp, vertical = 32.dp),
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .align(Alignment.Center),
            ) {
                Box(
                    modifier = Modifier
                        .size(58.dp)
                        .background(FieldLime, RoundedCornerShape(18.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Engineering,
                        contentDescription = null,
                        tint = FieldTealDark,
                        modifier = Modifier.size(32.dp),
                    )
                }
                Spacer(Modifier.height(22.dp))
                Text(
                    text = "FieldOps",
                    color = Color.White,
                    fontSize = 34.sp,
                    fontWeight = FontWeight.Bold,
                )
                Text(
                    text = "Tu jornada técnica, bajo control.",
                    color = Color.White.copy(alpha = 0.76f),
                    style = MaterialTheme.typography.titleMedium,
                )
                Spacer(Modifier.height(28.dp))

                Card(
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    shape = RoundedCornerShape(24.dp),
                ) {
                    Column(
                        modifier = Modifier.padding(22.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp),
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                Modifier
                                    .size(9.dp)
                                    .background(FieldLime, CircleShape),
                            )
                            Text(
                                text = if (demoMode) " DEMO INTERACTIVA" else " ACCESO SEGURO",
                                color = MaterialTheme.colorScheme.primary,
                                style = MaterialTheme.typography.labelMedium,
                                fontWeight = FontWeight.Bold,
                            )
                        }
                        Text(
                            text = "Inicia tu ruta",
                            style = MaterialTheme.typography.headlineSmall,
                            fontWeight = FontWeight.Bold,
                        )
                        Text(
                            text = "Consulta órdenes, registra avances y trabaja incluso sin conexión.",
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                        OutlinedTextField(
                            value = email,
                            onValueChange = { email = it },
                            label = { Text("Correo") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth(),
                        )
                        OutlinedTextField(
                            value = password,
                            onValueChange = { password = it },
                            label = { Text("Contraseña") },
                            singleLine = true,
                            visualTransformation = PasswordVisualTransformation(),
                            modifier = Modifier.fillMaxWidth(),
                        )
                        Button(
                            onClick = { onLogin(email, password) },
                            enabled = !loggingIn,
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(52.dp),
                            shape = RoundedCornerShape(14.dp),
                        ) {
                            Icon(Icons.Outlined.Bolt, contentDescription = null)
                            Text(if (loggingIn) "  Ingresando…" else "  Ingresar")
                        }
                        if (demoMode) {
                            OutlinedButton(
                                onClick = onUseDemo,
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(14.dp),
                            ) {
                                Icon(Icons.Outlined.CloudDone, contentDescription = null)
                                Text("  Usar acceso demo")
                            }
                            Text(
                                text = "${FieldOpsViewModel.DEMO_EMAIL}  ·  ${FieldOpsViewModel.DEMO_PASSWORD}",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.align(Alignment.CenterHorizontally),
                            )
                        }
                    }
                }
            }
        }
    }
}
