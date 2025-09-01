#!/bin/bash

# shellcheck disable=SC1091
echo "********************************* ✅ Construindo imagem docker para execução de Testes ✅ *********************************"

docker build -t canvi-pay-tests -f docker/Dockerfile .

echo "👉 Instalando dependências com o composer..."
docker run --rm -v $(pwd):/app -w /app canvi-pay-tests composer install --no-interaction --no-progress --prefer-dist

echo "********************************* ✅ Executando Testes ✅ *********************************"

#CONTAINER_NAME=$1
MAX_FAILURES=$1
REPORT_XML="report.xml"

#ls -ll $1

touch tempout.txt
echo "👉 Executando os testes PHPUnit..."
docker run --rm -v $(pwd):/app -w /app canvi-pay-tests ./vendor/bin/phpunit --testsuite canvi-pay --log-junit "$REPORT_XML" > tempout.txt
#docker exec "$CONTAINER_NAME" php -dxdebug.mode=off artisan test --parallel --log-junit report.xml --testsuite PipelineGitLab > tempout.txt

echo "👉 Extraindo dados do relatório $REPORT_XML"
echo "👉 local: $(pwd)"
echo "👉 Arquivo do relatorio: $(ls -ll $REPORT_XML)"

if [ -f "$REPORT_XML" ]; then
    TESTS=$(grep -oP 'tests="\K[0-9]+' "$REPORT_XML" | head -n1)
    ASSERTIONS=$(grep -oP 'assertions="\K[0-9]+' "$REPORT_XML" | head -n1)
    ERRORS=$(grep -oP 'errors="\K[0-9]+' "$REPORT_XML" | head -n1)
    FAILURES=$(grep -oP 'failures="\K[0-9]+' "$REPORT_XML" | head -n1)
    SKIPPED=$(grep -oP 'skipped="\K[0-9]+' "$REPORT_XML" | head -n1)
    TIME=$(grep -oP 'time="\K[0-9.]+' "$REPORT_XML" | head -n1)


    export TOTAL_FAILURES=0

    # Verifica se $FAILURES está vazio
    if [ -z "$FAILURES" ]; then
        FAILURES=0  # Atribui 0 se estiver vazio
    fi

    # Verifica se $ERRORS está vazio
    if [ -z "$ERRORS" ]; then
        ERRORS=0  # Atribui 0 se estiver vazio
    fi

    # Total é a soma de $ERRORS com $FAILURES
    # shellcheck disable=SC2004
    TOTAL_FAILURES=$(($FAILURES + $ERRORS))

    echo "┌──────────────────────────────── Resultados extraídos do report.xml ────────────────────────────────┐"

    printf "┌────────────────────────────────┐\n"
    printf "│ 📊 Resumo dos Testes           │\n"
    printf "├────────────────────────────────┤\n"
    printf "│ Tests             │ %-10s │\n" "$TESTS"
    printf "│ Assertions ✅      │ %-10s │\n" "$ASSERTIONS"
    printf "│ Failures ❌        │ %-10s │\n" "$FAILURES"
    printf "│ Errors 🔥         │ %-10s │\n" "$ERRORS"
    printf "│ Skipped           │ %-10s │\n" "$SKIPPED"
    printf "│ Total Failures    │ %-10s │\n" "$TOTAL_FAILURES"
    printf "│ Time ⏱            │ %-10s │\n" "$TIME"
    printf "└───────────────────┴────────────┘\n"


else
    echo "❗ Arquivo report.xml não encontrado 🔥"
    exit 1;
fi

if [ "$TOTAL_FAILURES"  -gt "$MAX_FAILURES" ]; then
    echo "Tests failed with $TOTAL_FAILURES total failures! 🔥💣"
    echo "└─────────────────────────────────────────────────────────────────────────────────────────────────────┘"
    exit 1;
else
    echo "Tests passed with $TOTAL_FAILURES total failures! ✅🟢"
    echo "└─────────────────────────────────────────────────────────────────────────────────────────────────────┘"
    exit 0;
fi
