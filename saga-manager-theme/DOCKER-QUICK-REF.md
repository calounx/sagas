# Docker Quick Reference - Saga Manager Testing

Quick reference for managing and monitoring the `saga_` prefixed Docker test environment.

## Container Names

| Service | Container Name | Purpose |
|---------|---------------|---------|
| Database | `saga_test_db` | MariaDB 11.4 test database |
| WordPress | `saga_test_wordpress` | WordPress with PHP 8.2 + Apache |
| PHPUnit | `saga_phpunit` | Test runner with Xdebug |
| Network | `saga_test_network` | Isolated test network |

## Quick Commands

### Monitor All Saga Containers

```bash
# List all saga containers
docker ps | grep saga_

# Show only saga containers with nice formatting
docker ps --filter "name=saga_" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

# Watch saga containers in real-time
watch -n 2 'docker ps --filter "name=saga_" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"'
```

### Container Logs

```bash
# All saga containers
docker logs saga_test_db
docker logs saga_test_wordpress
docker logs saga_phpunit

# Follow logs in real-time
docker logs -f saga_phpunit

# Last 100 lines
docker logs --tail 100 saga_test_db

# Since last hour
docker logs --since 1h saga_test_wordpress
```

### Container Stats

```bash
# All saga containers resource usage
docker stats saga_test_db saga_test_wordpress saga_phpunit

# With formatting
docker stats --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}" \
  saga_test_db saga_test_wordpress saga_phpunit
```

### Container Inspection

```bash
# Detailed container info
docker inspect saga_phpunit

# Get specific info
docker inspect saga_test_db --format='{{.State.Status}}'
docker inspect saga_phpunit --format='{{.NetworkSettings.IPAddress}}'
```

### Execute Commands

```bash
# Run PHPUnit tests
docker exec saga_phpunit vendor/bin/phpunit

# Open bash shell
docker exec -it saga_phpunit bash

# Run composer
docker exec saga_phpunit composer install

# Run wp-cli
docker exec saga_test_wordpress wp --allow-root plugin list
```

### Network Inspection

```bash
# Show network details
docker network inspect saga_test_network

# List containers on network
docker network inspect saga_test_network --format='{{range .Containers}}{{.Name}} {{.IPv4Address}}{{"\n"}}{{end}}'
```

## Makefile Commands (Recommended)

Use the Makefile for common operations:

```bash
make init              # Complete setup
make up                # Start containers
make down              # Stop containers
make test              # Run all tests
make shell-phpunit     # Shell in PHPUnit container
make logs              # View all logs
make status            # Show container status
make clean             # Remove everything
```

## Monitoring Workflows

### 1. Check Container Health

```bash
# Quick status check
docker ps --filter "name=saga_"

# Detailed health
docker inspect saga_test_db --format='{{.State.Health.Status}}'
```

### 2. Monitor Test Execution

```bash
# Terminal 1: Watch container stats
docker stats saga_phpunit

# Terminal 2: Follow logs
docker logs -f saga_phpunit

# Terminal 3: Run tests
make test
```

### 3. Debug Container Issues

```bash
# Check if containers are running
docker ps --filter "name=saga_" --filter "status=running"

# Check stopped containers
docker ps -a --filter "name=saga_" --filter "status=exited"

# View container events
docker events --filter "container=saga_phpunit"

# Inspect logs for errors
docker logs saga_test_db 2>&1 | grep -i error
```

### 4. Resource Monitoring

```bash
# CPU and Memory usage
docker stats --no-stream saga_test_db saga_test_wordpress saga_phpunit

# Disk usage
docker system df

# Volume usage
docker volume ls --filter "name=saga"
```

## Cleanup Commands

```bash
# Stop all saga containers
docker stop $(docker ps -q --filter "name=saga_")

# Remove all saga containers
docker rm $(docker ps -aq --filter "name=saga_")

# Remove saga network
docker network rm saga_test_network

# Remove saga volumes
docker volume rm $(docker volume ls -q --filter "name=saga")

# Complete cleanup (USE MAKEFILE INSTEAD)
make clean  # Safer - prompts for confirmation
```

## Docker Compose Commands

```bash
# Using docker-compose directly
docker-compose -f docker-compose.test.yml ps
docker-compose -f docker-compose.test.yml logs -f
docker-compose -f docker-compose.test.yml exec phpunit bash
docker-compose -f docker-compose.test.yml down
docker-compose -f docker-compose.test.yml up -d
```

## Common Scenarios

### Scenario 1: Database Not Accessible

```bash
# Check if database container is running
docker ps --filter "name=saga_test_db"

# Check database logs
docker logs saga_test_db | tail -50

# Restart database
docker restart saga_test_db

# Or use makefile
make restart
```

### Scenario 2: PHPUnit Container Issues

```bash
# Check container status
docker inspect saga_phpunit --format='{{.State.Status}}'

# View recent logs
docker logs --tail 100 saga_phpunit

# Restart container
docker restart saga_phpunit

# Enter container for debugging
docker exec -it saga_phpunit bash
```

### Scenario 3: Performance Issues

```bash
# Check resource usage
docker stats saga_test_db saga_test_wordpress saga_phpunit

# Check disk space
docker system df

# Prune unused resources
docker system prune -f

# Restart all containers
make restart
```

### Scenario 4: Network Issues

```bash
# Check network connectivity
docker network inspect saga_test_network

# Test database connection from PHPUnit container
docker exec saga_phpunit ping -c 3 saga_test_db

# Restart network (recreate containers)
make down && make up
```

## Environment Variables

Access environment variables in containers:

```bash
# View all environment variables
docker exec saga_phpunit env

# Get specific variable
docker exec saga_phpunit printenv WP_TESTS_DIR
docker exec saga_test_wordpress printenv WORDPRESS_DB_HOST
```

## Tips & Best Practices

1. **Use Makefile**: Prefer `make` commands over direct Docker commands
2. **Monitor Logs**: Always check logs when debugging issues
3. **Resource Limits**: Monitor stats to ensure containers have enough resources
4. **Clean Regularly**: Run `make clean` periodically to free up disk space
5. **Network Issues**: Restart containers if connectivity problems occur
6. **Database Health**: Check database health before running tests

## Troubleshooting Quick Reference

| Issue | Command | Solution |
|-------|---------|----------|
| Container not starting | `docker logs saga_phpunit` | Check logs for errors |
| Port already in use | `lsof -i :3307` | Stop conflicting service |
| Database connection failed | `docker restart saga_test_db` | Restart database |
| Out of disk space | `docker system prune -af` | Cleanup unused resources |
| Tests hanging | `docker exec saga_phpunit ps aux` | Check running processes |
| Permission errors | `chmod -R 777 tests/results/` | Fix permissions |

## Additional Resources

- **Main Testing Guide**: [TESTING-GUIDE.md](./TESTING-GUIDE.md)
- **Test Suite Details**: [tests/README.md](./tests/README.md)
- **Makefile Help**: Run `make help`
- **Docker Compose Docs**: https://docs.docker.com/compose/
- **PHPUnit Docs**: https://phpunit.de/documentation.html

---

## Summary

**Quick Start:** `make init`

**Monitor Containers:** `docker ps | grep saga_`

**View Logs:** `docker logs -f saga_phpunit`

**Run Tests:** `make test`

**Cleanup:** `make clean`

All containers use the `saga_` prefix for easy identification and monitoring!
