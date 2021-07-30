from flask import Flask

app = Flask(__name__)

@app.route("/")
def hello_world():
    return "<p>Hello, World!</p>"

@app.route("/election/2010")
def send_2010_data():
    # Get data from 2010.json file and load HTML page
    pass

@app.route("/election/2015")
def send_2015_data():
    # Get data from 2010.json file and load HTML page
    pass

@app.route("/election/2017")
def send_2017_data():
    # Get data from 2010.json file and load HTML page
    pass

# Maybe add the 2019 election?