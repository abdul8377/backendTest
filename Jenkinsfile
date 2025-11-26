pipeline {
  agent any

  environment {
    repoUrl          = 'https://github.com/abdul8377/backendTest.git'
    credentialsId    = 'github-creds'      // <--- cámbialo si tu ID es otro
    sonarServerName  = 'sonarqube'         // <--- debe coincidir con Manage Jenkins → System
    scannerToolName  = 'SonarScanner'      // <--- debe coincidir con Global Tool Configuration
    phpDockerImage   = 'ghcr.io/shivammathur/php:8.3'  // imagen PHP con install-php-extensions
  }

  options { timestamps() }

  stages {
    stage('Clone') {
      steps {
        timeout(time: 2, unit: 'MINUTES') {
          git branch: 'main', credentialsId: "${credentialsId}", url: "${repoUrl}"
        }
      }
    }

    stage('Build') {
      steps {
        timeout(time: 8, unit: 'MINUTES') {
          script {
            def img = docker.image("${phpDockerImage}")
            img.pull()
            img.inside('-u 0') {
              sh '''
                set -e
                php -v

                # Instalar extensiones mínimas
                install-php-extensions mbstring pdo_sqlite zip xml ctype tokenizer

                composer --version || true
                # Si la imagen no trae composer, lo descargamos
                if ! command -v composer >/dev/null 2>&1; then
                  EXPECTED_SIG="$(wget -q -O - https://composer.github.io/installer.sig)"
                  php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
                  ACTUAL_SIG="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
                  [ "$EXPECTED_SIG" = "$ACTUAL_SIG" ] || { echo 'Firma inválida de composer'; exit 1; }
                  php composer-setup.php --install-dir=/usr/local/bin --filename=composer
                  rm composer-setup.php
                fi

                composer install --no-interaction --prefer-dist
              '''
            }
          }
        }
      }
    }

    stage('Test') {
      steps {
        timeout(time: 10, unit: 'MINUTES') {
          script {
            def img = docker.image("${phpDockerImage}")
            img.inside('-u 0') {
              sh '''
                set -e
                # Usar SQLite para CI
                cp -f .env.testing .env || true
                sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env || true
                mkdir -p database
                : > database/database.sqlite

                php artisan key:generate || true
                php artisan config:clear || true
                php artisan migrate --force || true

                mkdir -p storage/coverage storage/test-reports
                vendor/bin/phpunit --coverage-clover storage/coverage/clover.xml \
                                   --log-junit storage/test-reports/junit.xml
              '''
            }
          }
        }
      }
      post {
        always {
          junit 'storage/test-reports/junit.xml'
          archiveArtifacts artifacts: 'storage/coverage/clover.xml', fingerprint: true
        }
      }
    }

    stage('Sonar') {
      steps {
        timeout(time: 4, unit: 'MINUTES') {
          withSonarQubeEnv("${sonarServerName}") {
            script {
              def scannerHome = tool name: "${scannerToolName}", type: 'hudson.plugins.sonar.SonarRunnerInstallation'
              sh """
                "${scannerHome}/bin/sonar-scanner" \
                  -Dsonar.projectKey=laravel-app \
                  -Dsonar.sources=app,routes,config,database \
                  -Dsonar.exclusions=vendor/**,storage/**,bootstrap/**,node_modules/**,public/** \
                  -Dsonar.php.coverage.reportPaths=storage/coverage/clover.xml
              """
            }
          }
        }
      }
    }

    stage('Quality gate') {
      steps {
        sleep(10)
        timeout(time: 4, unit: 'MINUTES') {
          waitForQualityGate abortPipeline: true
        }
      }
    }

    stage('Deploy') {
      steps {
        timeout(time: 8, unit: 'MINUTES') {
          echo 'Aquí iría tu paso de despliegue (rsync, docker compose, k8s, etc.), condicionado a Quality Gate OK.'
        }
      }
    }
  }
}
