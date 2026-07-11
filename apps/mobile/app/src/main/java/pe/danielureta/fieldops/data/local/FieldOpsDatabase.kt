package pe.danielureta.fieldops.data.local

import androidx.room.Database
import androidx.room.RoomDatabase

@Database(
    entities = [
        ServiceOrderEntity::class,
        EvidenceEntity::class,
        PendingMutationEntity::class,
    ],
    version = 2,
    exportSchema = false,
)
abstract class FieldOpsDatabase : RoomDatabase() {
    abstract fun serviceOrderDao(): ServiceOrderDao
    abstract fun evidenceDao(): EvidenceDao
    abstract fun pendingMutationDao(): PendingMutationDao
}
