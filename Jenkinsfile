pipeline {
  agent any

  environment {
    repoUrl         = 'https://github.com/abdul8377/backendTest.git'
    credentialsId   = 'github-creds'
    sonarServerName = 'sonarqube'
    scannerToolName = 'SonarScanner'
  }

  options { timestamps() }

  stages {
    stage('Clone') {
      steps {
        git branch: 'main', credentialsId: "${credentialsId}", url: "${repoUrl}"
      }
    }

    stage('Build (Composer)') {
      steps {
        sh '''
          set -e
          php -v
          composer --version
          composer install --no-interaction --prefer-dist
        '''
      }
    }

    stage('Test (PHPUnit + coverage)') {
      steps {
        sh '''
          set -e
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
      post {
        always {
          junit 'storage/test-reports/junit.xml'
          archiveArtifacts artifacts: 'storage/coverage/clover.xml', fingerprint: true
        }
      }
    }

    stage('Sonar') {
      steps {
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

    stage('Quality Gate') {
      steps {
        timeout(time: 10, unit: 'MINUTES') {
          waitForQualityGate abortPipeline: true
        }
      }
    }
  }
}
