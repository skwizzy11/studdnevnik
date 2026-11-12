// Пайплайн СтудДневника. Jenkins берёт этот файл и манифесты из k8s/
// прямо из репозитория на GitHub (Pipeline script from SCM) и запускается
// сам после каждого git push (раз в минуту проверяет репозиторий).
//
// База данных доступна только по порту 33006 (k8s/db-service.yaml).
// Порт, по которому приложение ходит в базу, задаётся в коде —
// переменная DB_PORT в k8s/backend.yaml:
//   * 33006 — база отвечает → разворачиваются фронтенд и бэкенд, сборка SUCCESS;
//   * 3306  — база на этом порту не отвечает → разворачивается только фронтенд,
//             бэкенд выключается (0 подов), сайт открывается с плашкой
//             «База данных недоступна», сборка UNSTABLE.
//
// Этапы:
//   1. Проверка Kubernetes, чтение порта из k8s/backend.yaml.
//   2. Сервис базы на порту 33006.
//   3. Проверка базы на порту из кода.
//   4. Деплой фронтенда (всегда).
//   5. Деплой бэкенда (только если база доступна) или его выключение.
//   6. Финальная проверка.

pipeline {
    agent {
        kubernetes {
            yaml '''
                apiVersion: v1
                kind: Pod
                spec:
                  containers:
                  - name: tools
                    image: alpine/k8s:1.27.4
                    command: ['cat']
                    tty: true
                    resources:
                      requests:
                        cpu: "100m"
                        memory: "128Mi"
                  - name: jnlp
                    image: jenkins/inbound-agent:latest
                    # Для тега latest Kubernetes по умолчанию каждый раз
                    # обращается к Docker Hub; берём образ, уже лежащий на ноде.
                    imagePullPolicy: IfNotPresent
                    args: ['$(JENKINS_SECRET)', '$(JENKINS_NAME)']
                    resources:
                      requests:
                        cpu: "50m"
                        memory: "256Mi"
            '''
        }
    }

    // GitHub не может достучаться до Jenkins во внутренней сети VirtualBox,
    // поэтому Jenkins сам раз в минуту проверяет, нет ли новых коммитов.
    triggers {
        pollSCM('* * * * *')
    }

    environment {
        KUBECONFIG    = credentials('kubeconfig-secret-id')
        NS            = 'default'
        DB_SERVICE    = 'db'
        DB_HOST       = 'db.default.svc.cluster.local'
        FRONT_SERVICE = 'crud-frontend-svc'
    }

    stages {
        stage('Check Kubernetes') {
            steps {
                container('tools') {
                    sh 'kubectl get nodes -o wide'
                    sh 'kubectl get namespaces'
                    echo "Код взят из репозитория: ${env.GIT_URL ?: 'GitHub'}, коммит ${env.GIT_COMMIT ?: '-'}"
                    sh 'git log -1 --format="Последний коммит: %h %an — %s" || true'
                    script {
                        // Порт базы берём из кода: значение DB_PORT в k8s/backend.yaml
                        env.DB_PORT = sh(
                            script: "grep -A1 'name: DB_PORT' k8s/backend.yaml | grep -o '[0-9][0-9]*' | head -1",
                            returnStdout: true
                        ).trim()
                        if (env.DB_PORT == '') {
                            error("В k8s/backend.yaml не найдена переменная DB_PORT!")
                        }
                        echo "Порт базы данных из кода (k8s/backend.yaml): ${env.DB_PORT}"
                    }
                }
            }
        }

        stage('Database Service :33006') {
            steps {
                container('tools') {
                    // В методичке сервис db создавался как ExternalName на порту
                    // 3306. Его нельзя переделать «на месте», поэтому удаляем
                    // старый вариант и создаём сервис из репозитория.
                    sh '''
                        TYPE=$(kubectl get service db -n "$NS" -o jsonpath='{.spec.type}' 2>/dev/null || true)
                        PORTS=$(kubectl get service db -n "$NS" -o jsonpath='{.spec.ports[*].port}' 2>/dev/null || true)
                        echo "Текущий сервис db: тип=${TYPE:-нет}, порты=${PORTS:-нет}"
                        if [ -n "$TYPE" ] && { [ "$TYPE" != "ClusterIP" ] || [ "$PORTS" != "33006" ]; }; then
                            echo "Пересоздаём сервис db: база должна быть доступна только на 33006"
                            kubectl delete service db -n "$NS"
                        fi
                        kubectl apply -f k8s/db-service.yaml
                        kubectl get service db -n "$NS" -o wide
                    '''
                }
            }
        }

        stage('Test Database Connection') {
            steps {
                container('tools') {
                    script {
                        echo "Проверка базы данных: ${env.DB_HOST}:${env.DB_PORT}"
                        def problem = null

                        // 1. Имя сервиса резолвится в DNS кластера
                        if (sh(script: "nslookup ${env.DB_HOST}", returnStatus: true) != 0) {
                            problem = 'имя сервиса не резолвится (DNS)'
                        }

                        // 2. У сервиса есть endpoint'ы — селектор находит поды ProxySQL
                        if (!problem) {
                            def endpoint = sh(
                                script: "kubectl get endpoints ${env.DB_SERVICE} -n ${env.NS} -o jsonpath='{.subsets[0].addresses[0].ip}' 2>/dev/null",
                                returnStdout: true
                            ).trim()
                            if (endpoint == '') {
                                sh "kubectl get service ${env.DB_SERVICE} -n ${env.NS} -o jsonpath='{.spec.selector}' || true"
                                problem = "у сервиса ${env.DB_SERVICE} нет активных endpoint'ов"
                            }
                        }

                        // 3. Кластер PXC жив: для кворума нужно большинство узлов (2 из 3)
                        if (!problem) {
                            def pxcRunning = sh(
                                script: "kubectl get pods -n ${env.NS} -l app.kubernetes.io/component=pxc --field-selector=status.phase=Running --no-headers 2>/dev/null | wc -l",
                                returnStdout: true
                            ).trim().toInteger()
                            echo "Узлов PXC в статусе Running: ${pxcRunning} из 3"
                            if (pxcRunning < 2) {
                                problem = 'у кластера PXC нет кворума'
                            }
                        }

                        // 4. Выбранный порт принимает соединения
                        if (!problem) {
                            if (sh(script: "nc -z -w 5 ${env.DB_HOST} ${env.DB_PORT}", returnStatus: true) != 0) {
                                problem = "порт ${env.DB_PORT} не принимает соединения (база работает только на 33006)"
                            }
                        }

                        if (problem) {
                            env.DB_OK = 'false'
                            unstable("База данных недоступна: ${problem}. Будет развёрнут только фронтенд.")
                        } else {
                            env.DB_OK = 'true'
                            echo "База данных доступна на порту ${env.DB_PORT}"
                        }
                    }
                }
            }
        }

        stage('Deploy Frontend') {
            steps {
                container('tools') {
                    sh "kubectl apply -f k8s/frontend.yaml"
                    sh "kubectl rollout restart deployment/crud-frontend -n ${env.NS}"
                    sh "kubectl rollout status deployment/crud-frontend -n ${env.NS} --timeout=180s"
                }
            }
        }

        stage('Deploy Backend') {
            when { expression { env.DB_OK == 'true' } }
            steps {
                container('tools') {
                    sh "kubectl apply -f k8s/backend.yaml"
                    sh "kubectl rollout restart deployment/crud-api -n ${env.NS}"
                    sh "kubectl rollout status deployment/crud-api -n ${env.NS} --timeout=180s"
                }
            }
        }

        stage('Backend Off') {
            when { expression { env.DB_OK != 'true' } }
            steps {
                container('tools') {
                    echo "База недоступна на порту ${env.DB_PORT} — бэкенд выключается, работает только фронтенд"
                    sh "kubectl scale deployment/crud-api --replicas=0 -n ${env.NS} || true"
                    sh "kubectl wait --for=delete pod -l app=crud-api -n ${env.NS} --timeout=60s || true"
                }
            }
        }

        stage('Final Health Check') {
            steps {
                container('tools') {
                    script {
                        sh "kubectl get pods -n ${env.NS} -l 'app in (crud-api,crud-frontend)' -o wide"

                        // Фронтенд должен открываться в любом случае
                        def frontIP = sh(
                            script: "kubectl get service ${env.FRONT_SERVICE} -n ${env.NS} -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null",
                            returnStdout: true
                        ).trim()
                        if (frontIP == '') {
                            error("LoadBalancer не получил External IP! Проверьте MetalLB.")
                        }
                        def code = sh(
                            script: "curl -s -o /dev/null -w '%{http_code}' -m 10 http://${frontIP}/",
                            returnStdout: true
                        ).trim()
                        echo "Фронтенд http://${frontIP}/ — HTTP ${code}"
                        if (code != '200') {
                            error("Фронтенд недоступен!")
                        }

                        def health = sh(
                            script: "curl -s -m 15 http://${frontIP}/check_connection.php || true",
                            returnStdout: true
                        ).trim()
                        echo "Ответ check_connection.php: ${health ?: '(пусто)'}"

                        if (env.DB_OK == 'true') {
                            if (!health.contains('"status":"ok"')) {
                                error("Приложение запущено, но не может работать с базой данных!")
                            }
                            echo "Работают фронтенд и бэкенд, база доступна на порту ${env.DB_PORT}"
                        } else {
                            echo "Работает только фронтенд: на странице плашка «База данных недоступна»"
                        }
                    }
                }
            }
        }
    }

    post {
        always {
            script {
                // Если агент не запустился или не скачал код, контейнера
                // tools уже нет — тогда диагностику просто пропускаем.
                try {
                    container('tools') {
                        echo "=== ДИАГНОСТИЧЕСКАЯ ИНФОРМАЦИЯ ==="
                        sh "kubectl get pods -n ${env.NS} -o wide || true"
                        sh "kubectl get service db crud-api ${env.FRONT_SERVICE} -n ${env.NS} || true"
                    }
                } catch (err) {
                    echo "Диагностика пропущена: агент недоступен"
                }
            }
        }
        success {
            echo "Деплой успешно завершён: фронтенд и бэкенд работают, база на порту ${env.DB_PORT}"
        }
        unstable {
            echo "Деплой частичный: работает только фронтенд (база недоступна на порту ${env.DB_PORT})"
        }
        failure {
            echo "Деплой завершился с ошибками"
            script {
                try {
                    container('tools') {
                        sh "kubectl describe deployment crud-api crud-frontend -n ${env.NS} || true"
                        sh "kubectl logs -l app=crud-api -n ${env.NS} --tail=30 || true"
                    }
                } catch (err) {
                    echo "Диагностика пропущена: агент недоступен"
                }
            }
        }
    }
}
