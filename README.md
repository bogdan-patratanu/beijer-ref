# Task Assignment Optimization System

## 📋 Requirements

- Docker & Docker Compose
- Make (optional, for convenience commands)

## 🚀 Quick Start

### 1. Clone and Setup

```bash
git clone git@github.com:bogdan-patratanu/beijer-ref.git
cd beijer-ref
```

### 2. Start the Application

```bash
# Initialize environment and build containers
make docker-build

# Download libraries and load data
make app-init

```
## 💻 Usage

### REST API Endpoints

The application automatically starts with REST API endpoints available:

```bash
# Minimum Cost Optimization
curl http://localhost/api/optimize/cost

# Minimum Makespan Optimization
curl http://localhost/api/optimize/makespan

# Get all employees
curl http://localhost/api/employees

# Get all tasks
curl http://localhost/api/tasks

# Health check
curl http://localhost/api/health
```


### CLI Commands

Inside the container (`make bash`), run:

```bash
# Minimum Cost Optimization
php bin/console app:optimize-tasks cost

# Minimum Makespan Optimization
php bin/console app:optimize-tasks makespan
```

## 🧪 Running Tests

```bash
make bash

# Inside the container
./vendor/bin/phpunit

```
