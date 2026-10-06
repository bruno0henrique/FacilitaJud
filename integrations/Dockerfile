FROM python:3.14-alpine
WORKDIR /app
COPY service.py ./
USER 65534
EXPOSE 8001
CMD ["python", "service.py"]
