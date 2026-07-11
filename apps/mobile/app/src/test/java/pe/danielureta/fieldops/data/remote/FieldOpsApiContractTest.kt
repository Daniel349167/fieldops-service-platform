package pe.danielureta.fieldops.data.remote

import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

class FieldOpsApiContractTest {
    private lateinit var server: MockWebServer
    private var token: String? = null
    private lateinit var api: FieldOpsApi

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()
        api = NetworkFactory.create(
            baseUrl = server.url("/").toString(),
            tokenProvider = object : TokenProvider {
                override fun token(): String? = token
            },
        )
    }

    @After
    fun tearDown() {
        server.shutdown()
    }

    @Test
    fun `login uses Laravel v1 contract`() {
        server.enqueue(jsonResponse(LOGIN_RESPONSE))

        val response = kotlinx.coroutines.runBlocking {
            api.login(LoginRequest("tecnico@fieldops.test", "FieldOps2026!"))
        }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/auth/login", request.path)
        assertTrue(request.body.readUtf8().contains("\"device_name\":\"fieldops-android\""))
        assertEquals("sanctum-token", response.data.token)
    }

    @Test
    fun `work orders and versioned transition send bearer and idempotency key`() {
        token = "sanctum-token"
        server.enqueue(jsonResponse(WORK_ORDERS_RESPONSE))
        server.enqueue(jsonResponse(WORK_ORDER_RESPONSE))

        val page = kotlinx.coroutines.runBlocking { api.getWorkOrders() }
        val listRequest = server.takeRequest()
        assertEquals("/api/v1/work-orders?per_page=100", listRequest.path)
        assertEquals("Bearer sanctum-token", listRequest.getHeader("Authorization"))
        assertEquals(7L, page.data.single().version)

        val transitioned = kotlinx.coroutines.runBlocking {
            api.transitionWorkOrder(
                id = "01JFIELDOPS",
                idempotencyKey = "mutation-123",
                body = TransitionWorkOrderRequest(toStatus = "in_progress", version = 7),
            )
        }
        val transitionRequest = server.takeRequest()
        assertEquals("POST", transitionRequest.method)
        assertEquals("/api/v1/work-orders/01JFIELDOPS/transition", transitionRequest.path)
        assertEquals("mutation-123", transitionRequest.getHeader("Idempotency-Key"))
        assertEquals("Bearer sanctum-token", transitionRequest.getHeader("Authorization"))
        val body = transitionRequest.body.readUtf8()
        assertTrue(body.contains("\"to_status\":\"in_progress\""))
        assertTrue(body.contains("\"version\":7"))
        assertEquals(8L, transitioned.data.version)
    }

    private fun jsonResponse(body: String) = MockResponse()
        .setResponseCode(200)
        .setHeader("Content-Type", "application/json")
        .setBody(body)

    private companion object {
        const val LOGIN_RESPONSE = """
            {"data":{"token":"sanctum-token","token_type":"Bearer","expires_at":"2026-08-09T12:00:00Z","user":{"id":2,"name":"Maria Tecnica","email":"tecnico@fieldops.test","role":"technician"}}}
        """
        const val WORK_ORDER = """
            {"id":"01JFIELDOPS","title":"Mantenimiento POS","description":"Revisar terminal","customer":{"name":"Market Central","phone":"+51 999 333 444","email":null},"address":{"line":"Jr. de la Union 650","district":"Cercado de Lima","city":"Lima","latitude":null,"longitude":null},"priority":"normal","status":"assigned","scheduled_at":"2026-07-10T16:00:00+00:00","version":7,"created_at":"2026-07-10T12:00:00+00:00","updated_at":"2026-07-10T12:00:00+00:00","deleted_at":null}
        """
        const val WORK_ORDERS_RESPONSE = """{"data":[$WORK_ORDER],"links":{},"meta":{}}"""
        const val WORK_ORDER_RESPONSE = """{"data":{"id":"01JFIELDOPS","title":"Mantenimiento POS","description":"Revisar terminal","customer":{"name":"Market Central","phone":"+51 999 333 444","email":null},"address":{"line":"Jr. de la Union 650","district":"Cercado de Lima","city":"Lima","latitude":null,"longitude":null},"priority":"normal","status":"in_progress","scheduled_at":"2026-07-10T16:00:00+00:00","version":8,"updated_at":"2026-07-10T12:05:00+00:00"}}"""
    }
}
