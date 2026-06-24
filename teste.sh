#!/bin/bash
#
# Script de testes com base no eml.json criado na raiz do projeto
#
# Autor: Matheus Rocha  Data:23/06/2026
#

curl -X POST http://localhost:8000 -H "Content-Type: application/json" -d @eml.json
