import json

def convert_coordinates(original_x, original_y):
    """
    Converts original coordinates to new coordinates based on a linear transformation
    derived from the sample points.
    """
    # Transformation parameters calculated from the points
    a = 3.25170375833
    b = 6.4389923353
    c = -3.2639995636
    d = 1625.24543224

    new_x = a * original_x + b
    new_y = c * original_y + d

    return new_x, new_y




file_path = "mapa punkty.json"

# Now, load the original_points from the JSON file
try:
    with open(file_path, "r") as f:
        loaded_points = json.load(f)
    # print("Successfully loaded points from points.json")
    # print(loaded_points)
except FileNotFoundError:
    print(f"Error: The file {file_path} was not found.")
except json.JSONDecodeError:
    print(f"Error: Could not decode JSON from the file {file_path}.")


class Point:
    spaceCode: str
    poiSpaceTypeName: str
    poiSpaceCategoryName: str
    hotelingSpace: str
    areaName: str
    toolTip: str
    bookable: str
    originalXCoordinate: float
    originalYCoordinate: float
    xCoordinate: float
    yCoordinate: float

    def __init__(self, data):
        self.spaceCode = data.get("spaceCode", "")
        self.poiSpaceTypeName = data.get("poiSpaceTypeName", "")
        self.poiSpaceCategoryName = data.get("poiSpaceCategoryName", "")
        self.hotelingSpace = data.get("hotelingSpace", "")
        self.areaName = data.get("areaName", "")
        self.toolTip = data.get("toolTip", "")
        self.bookable = data.get("bookable", "")
        self.originalXCoordinate = data.get("originalXCoordinate", 0.0)
        self.originalYCoordinate = data.get("originalYCoordinate", 0.0)
        self.xCoordinate = data.get("xCoordinate", 0.0)
        self.yCoordinate = data.get("yCoordinate", 0.0)

    def get_output(self):
        return {
            "name": self.spaceCode,
            "description": self.poiSpaceTypeName + " - " + self.areaName,
            "bookable": 1 if self.hotelingSpace == "Y" else 0,
            "x_coordinate": self.xCoordinate,
            "y_coordinate": self.yCoordinate,
        }


calculated_points = []

for point in loaded_points[0].get("poiInterfaceDTOs"):
    original_x = point.get("xCoordinate")
    original_y = point.get("yCoordinate")
    converted_x, converted_y = convert_coordinates(original_x, original_y)
    calculated_points.append(
        Point(
            {
                "originalXCoordinate": original_x,
                "originalYCoordinate": original_y,
                "xCoordinate": converted_x,
                "yCoordinate": converted_y,
                "spaceCode": point.get("spaceCode"),
                "poiSpaceTypeName": point.get("poiSpaceTypeName"),
                "poiSpaceCategoryName": point.get("poiSpaceCategoryName"),
                "hotelingSpace": point.get("hotelingSpace"),
                "areaName": point.get("areaName"),
                "toolTip": point.get("toolTip"),
                "bookable": point.get("bookable"),
            }
        )
    )

with open("calculated_points.json", "w") as f:
    json.dump([p.get_output() for p in calculated_points], f, indent=4)
