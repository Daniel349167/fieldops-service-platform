package pe.danielureta.fieldops.data.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import kotlinx.coroutines.flow.Flow

@Dao
interface ServiceOrderDao {
    @Query("SELECT * FROM service_orders ORDER BY CASE priority WHEN 'URGENT' THEN 0 WHEN 'HIGH' THEN 1 WHEN 'NORMAL' THEN 2 ELSE 3 END, schedule")
    fun observeAll(): Flow<List<ServiceOrderEntity>>

    @Query("SELECT * FROM service_orders WHERE id = :id")
    fun observeById(id: String): Flow<ServiceOrderEntity?>

    @Query("SELECT * FROM service_orders WHERE id = :id")
    suspend fun getById(id: String): ServiceOrderEntity?

    @Query("SELECT COUNT(*) FROM service_orders")
    suspend fun count(): Int

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(orders: List<ServiceOrderEntity>)

    @Query("UPDATE service_orders SET status = :status, version = :version, updatedAt = :updatedAt WHERE id = :id")
    suspend fun updateStatus(id: String, status: String, version: Long, updatedAt: Long)

}

@Dao
interface EvidenceDao {
    @Query("SELECT * FROM evidence WHERE orderId = :orderId ORDER BY capturedAt DESC")
    fun observeByOrder(orderId: String): Flow<List<EvidenceEntity>>

    @Query("SELECT * FROM evidence WHERE id = :id")
    suspend fun getById(id: String): EvidenceEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(evidence: EvidenceEntity)
}

@Dao
interface PendingMutationDao {
    @Query("SELECT COUNT(*) FROM pending_mutations")
    fun observeCount(): Flow<Int>

    @Query("SELECT * FROM pending_mutations ORDER BY createdAt, id")
    suspend fun getAll(): List<PendingMutationEntity>

    @Insert
    suspend fun insert(mutation: PendingMutationEntity)

    @Query("DELETE FROM pending_mutations WHERE id = :id")
    suspend fun delete(id: Long)

    @Query("DELETE FROM pending_mutations")
    suspend fun deleteAll()
}
