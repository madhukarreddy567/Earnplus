plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.earnplus.app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }

    kotlin {
        compilerOptions {
            jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_11
        }
    }

    packaging {
        resources {
            // Duplicate metadata files shipped in both the JVM and Android
            // variants of the same library; keep the first copy.
            excludes += "META-INF/**/LICENSE.txt"
            excludes += "META-INF/*.version"
            excludes += "META-INF/AL2.0"
            excludes += "META-INF/LGPL2.1"
        }
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.earnplus.app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.getByName("debug")
        }
    }

    // Kotlin-multiplatform jars (androidx.collection, annotation,
    // kotlinx-coroutines) each embed compiler metadata
    // (commonMain/…, nativeMain/…) that collides at merge time. These
    // files are never used by the Android runtime, so drop them.
    packaging {
        resources {
            excludes += "commonMain/**"
            excludes += "nativeMain/**"
            excludes += "iosMain/**"
            excludes += "watchosMain/**"
            excludes += "META-INF/kotlin-project-structure-metadata.json"
        }
    }
}

flutter {
    source = "../.."
}
