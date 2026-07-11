# FieldOps Mobile

Aplicación Android nativa para técnicos de campo. Implementa acceso, agenda de órdenes, detalle operativo, transiciones de estado y una cola offline persistente compatible con la API Laravel incluida en `apps/api`.

## Demo interactiva

- Usuario: `demo@fieldops.pe`
- Contraseña: `demo123`
- `FIELDOPS_DEMO_MODE=true` viene activado por defecto.
- **Simular captura** crea únicamente metadatos y un adjunto local marcado como demo en Room.
- El icono de nube permite simular pérdida y recuperación de conectividad.
- Las órdenes y mutaciones demo no requieren ni contactan un backend.

## Stack y arquitectura

- Kotlin, Jetpack Compose y Material 3.
- Arquitectura por capas: `presentation` → `domain` → `data`.
- Room como fuente local observable y cola persistente de mutaciones.
- Retrofit/OkHttp para el contrato HTTP.
- Sanctum Bearer persistido en preferencias privadas de la aplicación.
- Cola versionada: conserva `version` e `Idempotency-Key` por transición y actualiza la versión local con la respuesta del servidor.
- ViewModel + StateFlow para el estado de pantalla.
- Pruebas del flujo de acceso, el comportamiento offline y el contrato HTTP real.

```text
app/src/main/java/pe/danielureta/fieldops/
├── data/
│   ├── local/       # Room: órdenes, evidencias demo y cola versionada
│   ├── remote/      # Retrofit, DTOs Laravel y sesión Bearer
│   └── repository/  # Coordinación offline-first
├── domain/          # Modelos, reglas y contrato del repositorio
└── presentation/    # ViewModel y pantallas Compose
```

## Compilar y probar

Requisitos: JDK 17, Android SDK 35 y el Gradle Wrapper incluido.

```powershell
.\gradlew.bat assembleDebug
.\gradlew.bat test
.\gradlew.bat lintDebug
```

El APK queda en `app/build/outputs/apk/debug/app-debug.apk`.

## Usar la API Laravel real

La URL base apunta al dominio o al host raíz; el cliente agrega `/api/v1/...`:

```properties
FIELDOPS_API_BASE_URL=http://10.0.2.2:8000/
FIELDOPS_DEMO_MODE=false
```

Ejemplo sin editar archivos:

```powershell
.\gradlew.bat assembleDebug -PFIELDOPS_API_BASE_URL=http://10.0.2.2:8000/ -PFIELDOPS_DEMO_MODE=false
```

El manifest `debug` permite HTTP únicamente para desarrollo local con el emulador. La variante `release` mantiene la política segura de Android y debe usar HTTPS.

Credenciales del seeder local de `apps/api`:

- Técnico: `tecnico@fieldops.test` / `FieldOps2026!`
- Administrador: `admin@fieldops.test` / `FieldOps2026!`

Contrato usado por la app:

- `POST /api/v1/auth/login` con `email`, `password` y `device_name`.
- `GET /api/v1/work-orders?per_page=100` con `Authorization: Bearer ...`.
- `POST /api/v1/work-orders/{id}/transition` con `to_status`, `version` e `Idempotency-Key`.

El bootstrap remoto ocurre solo después de autenticar o restaurar una sesión previamente autenticada. Los DTO consumen los envelopes `{ "data": ... }` de Laravel.

La app móvil hace un bootstrap paginado inicial y luego empuja su cola local de transiciones. Esta versión no consume todavía `GET /api/v1/sync` ni mantiene un cursor de cambios incrementales; ese flujo está implementado en el backend, pero queda fuera del alcance del cliente Android actual.

## Alcance honesto de evidencia

Esta versión de portafolio no implementa CameraX ni la carga binaria a almacenamiento. Por seguridad semántica, una evidencia simulada **nunca se envía** al endpoint real. La función de captura se muestra solo en modo demo; en modo real se sincronizan exclusivamente transiciones de estado. Completar una orden que requiera evidencia dependerá de que exista una evidencia real registrada por un cliente que sí implemente esa carga.

Si falla la carga remota inicial, la aplicación informa el error y conserva cualquier copia local existente; nunca sustituye silenciosamente datos reales por órdenes demo.
