allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
}

// image_picker_android wants androidx.core:core:1.18.0 and
// androidx.activity:activity:1.12.4, neither of which is reachable from
// this build environment (offline Maven mirror). The photo-picker APIs
// it uses are stable, so pin the cached versions for every subproject
// (the :app-level force does not reach plugin modules). Revisit when
// the mirror carries the newer artifacts.
subprojects {
    configurations.all {
        resolutionStrategy {
            force("androidx.core:core:1.17.0")
            force("androidx.core:core-ktx:1.13.1")
            force("androidx.activity:activity:1.9.0")
        }
    }
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
