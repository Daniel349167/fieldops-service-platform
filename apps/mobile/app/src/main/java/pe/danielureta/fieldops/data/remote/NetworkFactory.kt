package pe.danielureta.fieldops.data.remote

import android.content.Context
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

interface TokenProvider {
    fun token(): String?
}

interface AuthSessionStore : TokenProvider {
    fun saveToken(token: String)
    fun clear()
    fun hasToken(): Boolean = !token().isNullOrBlank()
}

class SharedPreferencesAuthSessionStore(context: Context) : AuthSessionStore {
    private val preferences = context.getSharedPreferences("fieldops-auth", Context.MODE_PRIVATE)

    override fun token(): String? = preferences.getString(TOKEN_KEY, null)

    override fun saveToken(token: String) {
        preferences.edit().putString(TOKEN_KEY, token).apply()
    }

    override fun clear() {
        preferences.edit().remove(TOKEN_KEY).apply()
    }

    private companion object {
        const val TOKEN_KEY = "bearer-token"
    }
}

object NetworkFactory {
    fun create(baseUrl: String, tokenProvider: TokenProvider): FieldOpsApi {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BASIC
            redactHeader("Authorization")
        }
        val client = OkHttpClient.Builder()
            .addInterceptor { chain ->
                val request = chain.request()
                val token = tokenProvider.token()
                val authenticated = if (token.isNullOrBlank()) {
                    request
                } else {
                    request.newBuilder()
                        .header("Authorization", "Bearer $token")
                        .build()
                }
                chain.proceed(authenticated)
            }
            .addInterceptor(logging)
            .build()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(FieldOpsApi::class.java)
    }
}
