package pe.danielureta.fieldops.data.local

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(tableName = "service_orders")
data class ServiceOrderEntity(
    @PrimaryKey val id: String,
    val code: String,
    val title: String,
    val customer: String,
    val address: String,
    val schedule: String,
    val priority: String,
    val status: String,
    val description: String,
    val contactName: String,
    val contactPhone: String,
    val version: Long,
    val updatedAt: Long,
)

@Entity(
    tableName = "evidence",
    foreignKeys = [
        ForeignKey(
            entity = ServiceOrderEntity::class,
            parentColumns = ["id"],
            childColumns = ["orderId"],
            onDelete = ForeignKey.CASCADE,
        ),
    ],
    indices = [Index("orderId")],
)
data class EvidenceEntity(
    @PrimaryKey val id: String,
    val orderId: String,
    val fileName: String,
    val note: String,
    val capturedAt: Long,
    val simulated: Boolean,
)

@Entity(
    tableName = "pending_mutations",
    indices = [Index("orderId")],
)
data class PendingMutationEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val orderId: String,
    val type: String,
    val targetStatus: String? = null,
    val evidenceId: String? = null,
    val expectedVersion: Long,
    val idempotencyKey: String,
    val createdAt: Long,
)

object MutationType {
    const val STATUS = "STATUS"
    const val EVIDENCE = "EVIDENCE"
}
