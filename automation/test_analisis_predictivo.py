import os
import time
import allure
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

@allure.epic("Gestión de Calidad y Testing TMMi - Nivel 5")
@allure.feature("Módulo de Predicción de Deserción (Moodle)")
class TestMoodlePredictivo:

    @allure.severity(allure.severity_level.CRITICAL)
    def test_flujo_completo_moodle_ia(self):
        """
        Script de automatización para la verificación continua del Dashboard Predictivo Moodle.
        Alineado con las prácticas de optimización y prevención de defectos de TMMi Nivel 5.
        (Versión Demo: Pausas añadidas para visualización en vivo)
        """
        print("Iniciando prueba de TMMi Nivel 5: Verificación Continua del Módulo IA...")
        
        # Configuración del WebDriver (usamos Chrome por defecto)
        driver = webdriver.Chrome()
        
        try:
            # 1. Abrir de forma dinámica el archivo 'index.html'
            ruta_actual = os.path.abspath(os.path.dirname(__file__))
            ruta_html = os.path.join(ruta_actual, "index.html")
            driver.get(f"file:///{ruta_html}")
            
            # 2. Maximizar la ventana y esperar que la pantalla de Login cargue completamente
            driver.maximize_window()
            wait = WebDriverWait(driver, 10)
            print("Página cargada. Ingresando credenciales...")
            
            # Pausa 1: Esperar 3 segundos para que el jurado vea la pantalla de Login limpia
            time.sleep(3)
            
            with allure.step("Paso 1: Autenticación segura en Login Institucional Moodle"):
                # Esperar a que el input de usuario esté visible
                username_input = wait.until(EC.visibility_of_element_located((By.ID, "username")))
                
                # 3. Localizar el campo de usuario y escribir
                username_input.send_keys("profesor@tmmi.edu")
                
                # Pausa 2: Esperar 1.5 segundos después de escribir el usuario
                time.sleep(1.5)
                
                # 4. Localizar el campo de contraseña y escribir
                password_input = driver.find_element(By.ID, "password")
                password_input.send_keys("tmmi5opt")
                
                # Pausa 3: Esperar 2 segundos enteros para que el jurado note las credenciales puestas
                time.sleep(2)
                
                # 5. Localizar y hacer clic en el botón de ingreso
                btn_ingresar = driver.find_element(By.ID, "btn-ingresar")
                btn_ingresar.click()
                print("Credenciales enviadas. Verificando acceso al Dashboard...")

            with allure.step("Paso 2: Navegación y Selección del Curso de Testing en Área Personal"):
                # 6. Verificar transición de Login a Área Personal (Pantalla 2)
                area_personal = wait.until(EC.visibility_of_element_located((By.ID, "area-personal-screen")))
                print("Área Personal cargada exitosamente.")
                
                # Pausa extra: Mostrar el Área Personal al jurado
                time.sleep(2)
                
                # Hacer clic en la tarjeta del curso TMMi
                card_curso = driver.find_element(By.ID, "card-curso-tmmi")
                card_curso.click()
                print("Ingresando al panel predictivo del curso...")

            with allure.step("Paso 3: Verificación del Estado Inicial y Ejecución del Modelo IA Predictivo"):
                # 6.5. Verificar transición al Dashboard (Pantalla 3)
                dashboard = wait.until(EC.visibility_of_element_located((By.ID, "dashboard-screen")))
                
                # Assert del estado inicial: Verificar que los alumnos están "Sin evaluar"
                page_text = dashboard.text
                assert "Sin evaluar" in page_text, "Error TMMi: El estado inicial no es 'Sin evaluar'."
                print("[OK] Acceso exitoso. Estado inicial validado: 'Sin evaluar'.")
                
                # Pausa 4: Hacer una pausa de 3 segundos para explicar la tabla con el estado inicial
                time.sleep(3)
                
                # 7. Localizar y hacer clic en el botón "Ejecutar IA"
                btn_ejecutar = driver.find_element(By.ID, "btn-ejecutar")
                btn_ejecutar.click()
                print("Ejecutando algoritmo de IA predictivo. Esperando procesamiento...")
                
                # Pausa 5: Mantener la espera de 4 segundos para que se vea el estado en carga "Procesando..."
                time.sleep(4)

            with allure.step("Paso 4: Control Estadístico de Métricas de Riesgo en Tiempo Real"):
                # 9. Realizar las verificaciones finales (Asserts de TMMi Nivel 5)
                contador_alto = driver.find_element(By.ID, "contador-alto").text
                assert contador_alto == "1", f"Fallo en métrica: Se esperaba 1 en Riesgo Alto, pero se encontró {contador_alto}."
                
                riesgo_laura = driver.find_element(By.ID, "riesgo-laura").text
                assert "Alto Riesgo" in riesgo_laura, "Fallo en IA: No se identificó el 'Alto Riesgo' para Laura Gómez."
                
                print("[OK] Validación de IA exitosa: Métricas y estados actualizados correctamente.")
                print("[EXITO] Pipeline de pruebas TMMi Nivel 5 completado sin defectos.")
                
                # Pausa 6: Pausa final de 5 segundos para que los evaluadores vean los checks de éxito
                time.sleep(5)
                
        finally:
            # 10. Cerrar el navegador limpiamente al terminar
            print("Cerrando el navegador y limpiando sesión...")
            driver.quit()