# FieldOps Admin

Centro de operaciones responsive para supervisar órdenes de servicio en campo. Está construido con Vue 3, TypeScript y Vite, y funciona tanto con una API REST como con un conjunto local de datos demostrativos.

## Qué demuestra

- Resumen operativo con órdenes abiertas, urgencias, cumplimiento y carga del equipo.
- Búsqueda tolerante a tildes y filtros combinables por estado y prioridad.
- Tabla de escritorio y tarjetas móviles con detalle accesible por teclado.
- Evidencias, datos del cliente e historial de eventos por orden.
- Estados reales de carga, error, lista vacía y filtros sin coincidencias.
- Capa API tipada con timeout, token Bearer y errores de dominio.
- Modo demo identificado visualmente: sus datos no persisten ni representan clientes reales.

## Inicio rápido

Requiere Node.js 22 o superior.

```bash
npm install
cp .env.example .env
npm run dev
```

El modo demo es el comportamiento predeterminado. La aplicación abre en `http://localhost:4173`.

## Configuración

| Variable | Predeterminado | Uso |
| --- | --- | --- |
| `VITE_DEMO_MODE` | `true` | `true` sirve datos locales; `false` activa la API. |
| `VITE_API_URL` | `http://localhost:8000/api/v1` | URL base del backend; se normaliza a `/api/v1`. |
| `VITE_API_TOKEN` | vacío | Token Bearer opcional para desarrollo. |

Para comprobar el comportamiento de error de red, establece `VITE_DEMO_MODE=false` sin levantar el backend. El panel ofrecerá una acción de reintento sin sustituir el error por datos ficticios.

## Contrato HTTP

En modo API se consultan estos recursos:

- `GET /api/v1/dashboard`
- `GET /api/v1/work-orders`
- `GET /api/v1/work-orders/:id`
- `GET /api/v1/work-orders/:id/evidences`
- `GET /api/v1/work-orders/:id/timeline`

Las respuestas usan un sobre `{ "data": ..., "meta": ... }`. Los errores pueden incluir `{ "message": "...", "code": "..." }`. Cada solicitud expira después de 10 segundos y añade `Authorization: Bearer <token>` cuando existe un token.

## Calidad

```bash
npm run lint
npm run test
npm run build
```

La interfaz incluye enlace para saltar al contenido, foco visible, nombres accesibles, anuncios de carga/resultados, cierre con Escape, contención y restauración de foco en el diálogo, objetivos táctiles y reducción de movimiento según las preferencias del sistema.

## Contenedor

La imagen compila el frontend y lo sirve con nginx en el puerto `8080`.

```bash
docker build -t fieldops-admin .
docker run --rm -p 8080:8080 fieldops-admin
```

Para conectar una API durante el build:

```bash
docker build \
  --build-arg VITE_DEMO_MODE=false \
  --build-arg VITE_API_URL=https://api.example.com/api/v1 \
  -t fieldops-admin .
```

El contenedor expone `GET /health`, aplica fallback de SPA, comprime recursos y sirve los assets versionados con caché inmutable.

## Estructura

```text
src/
├── api/          # gateway HTTP y gateway demo
├── components/   # dashboard, filtros, lista y drawer
├── data/         # escenario demostrativo local
├── tests/        # pruebas de interfaz y reglas operativas
├── types/        # contratos del dominio y la API
└── utils/        # filtros, métricas y formato
```

El modo demo y el modo API implementan el mismo `OperationsGateway`, de modo que la interfaz no contiene bifurcaciones de infraestructura.
