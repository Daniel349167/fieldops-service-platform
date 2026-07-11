package pe.danielureta.fieldops.presentation.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

val FieldTeal = Color(0xFF117A73)
val FieldTealDark = Color(0xFF00504C)
val FieldLime = Color(0xFFB6DB61)
val FieldInk = Color(0xFF172625)
val FieldMist = Color(0xFFF3F7F4)
val FieldOrange = Color(0xFFE46F2B)

private val LightColors = lightColorScheme(
    primary = FieldTeal,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFC4F0EA),
    onPrimaryContainer = Color(0xFF00201E),
    secondary = Color(0xFF51634A),
    secondaryContainer = Color(0xFFD4E8C8),
    tertiary = FieldOrange,
    background = FieldMist,
    onBackground = FieldInk,
    surface = Color(0xFFFAFDF9),
    onSurface = FieldInk,
    surfaceVariant = Color(0xFFDCE5E0),
    outline = Color(0xFF707A75),
)

private val DarkColors = darkColorScheme(
    primary = Color(0xFF76D7CD),
    onPrimary = Color(0xFF003734),
    primaryContainer = FieldTealDark,
    secondary = Color(0xFFB8CCAD),
    secondaryContainer = Color(0xFF394B34),
    tertiary = Color(0xFFFFB68D),
    background = Color(0xFF0E1514),
    onBackground = Color(0xFFDEE4E0),
    surface = Color(0xFF121B1A),
    onSurface = Color(0xFFDEE4E0),
    surfaceVariant = Color(0xFF3F4946),
)

@Composable
fun FieldOpsTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = MaterialTheme.typography,
        content = content,
    )
}
