package pe.danielureta.fieldops.data.remote

import com.google.gson.annotations.SerializedName
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/** Retrofit contract that mirrors the Laravel API in apps/api. */
interface FieldOpsApi {
    @POST("api/v1/auth/login")
    suspend fun login(@Body body: LoginRequest): DataEnvelope<LoginDataDto>

    @GET("api/v1/work-orders")
    suspend fun getWorkOrders(
        @Query("per_page") perPage: Int = 100,
    ): PaginatedEnvelope<WorkOrderDto>

    @POST("api/v1/work-orders/{id}/transition")
    suspend fun transitionWorkOrder(
        @Path("id") id: String,
        @Header("Idempotency-Key") idempotencyKey: String,
        @Body body: TransitionWorkOrderRequest,
    ): DataEnvelope<WorkOrderDto>
}

data class DataEnvelope<T>(val data: T)

data class PaginatedEnvelope<T>(
    val data: List<T>,
)

data class LoginRequest(
    val email: String,
    val password: String,
    @SerializedName("device_name") val deviceName: String = "fieldops-android",
)

data class LoginDataDto(
    val token: String,
    @SerializedName("token_type") val tokenType: String,
    @SerializedName("expires_at") val expiresAt: String,
    val user: UserDto,
)

data class UserDto(
    val id: String,
    val name: String,
    val email: String,
    val role: String,
)

data class WorkOrderDto(
    val id: String,
    val title: String,
    val description: String?,
    val customer: CustomerDto,
    val address: AddressDto,
    val priority: String,
    val status: String,
    @SerializedName("scheduled_at") val scheduledAt: String?,
    val version: Long,
    @SerializedName("updated_at") val updatedAt: String?,
)

data class CustomerDto(
    val name: String,
    val phone: String?,
    val email: String?,
)

data class AddressDto(
    val line: String,
    val district: String?,
    val city: String?,
)

data class TransitionWorkOrderRequest(
    @SerializedName("to_status") val toStatus: String,
    val version: Long,
    val note: String? = null,
)
