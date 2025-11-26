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
            catchError(buildResult: 'UNSTABLE', stageResult: 'FAILURE') {
                sh '''
                    set -e
                    cp -f .env.testing .env
                    sed -i s/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/ .env
                    mkdir -p database storage/coverage storage/test-reports
                    php artisan key:generate
                    php artisan config:clear
                    php artisan migrate --force

                    vendor/bin/phpunit \
                      --coverage-clover storage/coverage/clover.xml \
                      --log-junit storage/test-reports/junit.xml
                '''
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
