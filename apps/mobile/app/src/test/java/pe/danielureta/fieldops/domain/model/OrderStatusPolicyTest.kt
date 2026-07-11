package pe.danielureta.fieldops.domain.model

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class OrderStatusPolicyTest {
    @Test
    fun `workflow allows only the immediate next operational state`() {
        assertEquals(listOf(OrderStatus.EN_ROUTE), OrderStatusPolicy.allowedNext(OrderStatus.ASSIGNED))
        assertTrue(OrderStatusPolicy.canTransition(OrderStatus.EN_ROUTE, OrderStatus.IN_PROGRESS))
        assertFalse(OrderStatusPolicy.canTransition(OrderStatus.ASSIGNED, OrderStatus.COMPLETED))
    }

    @Test
    fun `completed orders are terminal`() {
        assertTrue(OrderStatusPolicy.allowedNext(OrderStatus.COMPLETED).isEmpty())
        OrderStatus.entries.forEach { candidate ->
            assertFalse(OrderStatusPolicy.canTransition(OrderStatus.COMPLETED, candidate))
        }
    }
}
