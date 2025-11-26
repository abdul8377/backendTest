pipeline {
  agent any

  tools {
    // Debe existir en "Global Tool Configuration"
    sonarQubeScanner 'SonarScanner'
  }

  environment {
    SONARQUBE = 'sonarqube' // Nombre del servidor Sonar en Manage Jenkins → System
  }

  options { timestamps() }

  stages {
    stage('Checkout') {
      steps {
        git branch: 'main',
            url: 'https://github.com/abdul8377/backendTest.git',
            credentialsId: 'github-creds'
      }
    }

    stage('Prepare PHP & Composer') {
      steps {
        sh '''
          set -e
          php -v || { echo "❌ PHP no está instalado en el agente"; exit 1; }

          if ! command -v composer >/dev/null 2>&1; then
            echo "⬇️ Instalando Composer temporalmente..."
            EXPECTED_SIG="$(wget -q -O - https://composer.github.io/installer.sig)"
            php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
            ACTUAL_SIG="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
            [ "$EXPECTED_SIG" = "$ACTUAL_SIG" ] || { echo 'Firma de Composer inválida'; exit 1; }
            php composer-setup.php --install-dir=/usr/local/bin --filename=composer
            rm composer-setup.php
          fi

          composer -V
          composer install --no-interaction --prefer-dist
        '''
      }
    }

    stage('Prepare .env & DB') {
      steps {
        sh '''
          cp -f .env.testing .env || true
          php artisan key:generate || true
          php artisan config:clear || true

          # Si usas MySQL en testing, asegúrate que es accesible desde el agente.
          # Si prefieres SQLite para tests rápidos, descomenta estas 3 líneas y ajusta .env.testing:
          # touch database/database.sqlite
          # php -r "file_exists('database/database.sqlite') || exit(0);"
          # sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env

          php artisan migrate --env=testing --force
        '''
      }
    }

    stage('Run Tests (coverage)') {
      steps {
        sh '''
          mkdir -p storage/coverage storage/test-reports
          # PHPUnit 10+ suele estar en vendor/bin/phpunit; si no, usa php vendor/phpunit/phpunit/phpunit
          vendor/bin/phpunit --coverage-clover storage/coverage/clover.xml --log-junit storage/test-reports/junit.xml
        '''
      }
      post {
        always {
          junit 'storage/test-reports/junit.xml'
          archiveArtifacts artifacts: 'storage/coverage/clover.xml', fingerprint: true
        }
      }
    }

    stage('SonarQube Analysis') {
      steps {
        withSonarQubeEnv("${sonarqube}") {
          sh '''
            # Usa el scanner configurado como "scanner-latest"
            "${SONAR_SCANNER_HOME}/bin/sonar-scanner" \
              -Dsonar.projectKey=laravel-app \
              -Dsonar.sources=app,routes,config,database \
              -Dsonar.exclusions=vendor/**,storage/**,bootstrap/**,node_modules/**,public/** \
              -Dsonar.php.coverage.reportPaths=storage/coverage/clover.xml
            # Nota: No pasamos sonar.login aquí; lo maneja Jenkins por la integración del servidor.
            # Tampoco forzamos sonar.host.url; withSonarQubeEnv lo expone como SONAR_HOST_URL.
          '''
        }
      }
    }

    stage('Quality Gate') {
      steps {
        timeout(time: 10, unit: 'MINUTES') {
          waitForQualityGate abortPipeline: true
        }
      }
    }
  }

  post {
    success { echo '✅ Pipeline completado correctamente.' }
    failure { echo '❌ Falló el pipeline. Revisa logs en Jenkins.' }
  }
}
